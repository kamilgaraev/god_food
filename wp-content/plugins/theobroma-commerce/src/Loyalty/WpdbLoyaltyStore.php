<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

use DomainException;
use RuntimeException;

final class WpdbLoyaltyStore implements LoyaltyStore
{
    private string $accountTable;
    private string $ledgerTable;

    public function __construct(private readonly \wpdb $wpdb)
    {
        $this->accountTable = $wpdb->prefix . 'theobroma_loyalty_accounts';
        $this->ledgerTable = $wpdb->prefix . 'theobroma_loyalty_ledger';
    }

    public function mutate(
        int $userId,
        string $idempotencyKey,
        string $type,
        int $availableDeltaKopecks,
        int $reservedDeltaKopecks,
        int $orderId,
        array $context = []
    ): LoyaltyEntry {
        if ($userId < 1 || $idempotencyKey === '') {
            throw new DomainException('A customer and idempotency key are required.');
        }

        $this->wpdb->query('START TRANSACTION');
        try {
            $now = current_time('mysql', true);
            $inserted = $this->wpdb->query($this->wpdb->prepare(
                "INSERT IGNORE INTO {$this->accountTable} (user_id, available_kopecks, reserved_kopecks, updated_at) VALUES (%d, 0, 0, %s)",
                $userId,
                $now
            ));
            if ($inserted === false) {
                throw new RuntimeException('Unable to initialize loyalty account: ' . $this->wpdb->last_error);
            }

            $account = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT available_kopecks, reserved_kopecks FROM {$this->accountTable} WHERE user_id = %d FOR UPDATE",
                $userId
            ), ARRAY_A);
            if (!is_array($account)) {
                throw new RuntimeException('Unable to lock loyalty account.');
            }

            $existing = $this->findByKey($idempotencyKey);
            if ($existing instanceof LoyaltyEntry) {
                $this->wpdb->query('COMMIT');
                return $existing;
            }

            $available = (int) $account['available_kopecks'] + $availableDeltaKopecks;
            $reserved = (int) $account['reserved_kopecks'] + $reservedDeltaKopecks;
            if (($available < 0 && $type !== 'reverse-accrual') || $reserved < 0) {
                throw new DomainException('Insufficient loyalty balance for this operation.');
            }

            $updated = $this->wpdb->update(
                $this->accountTable,
                ['available_kopecks' => $available, 'reserved_kopecks' => $reserved, 'updated_at' => $now],
                ['user_id' => $userId],
                ['%d', '%d', '%s'],
                ['%d']
            );
            if ($updated === false) {
                throw new RuntimeException('Unable to update loyalty account: ' . $this->wpdb->last_error);
            }

            $written = $this->wpdb->insert(
                $this->ledgerTable,
                [
                    'user_id' => $userId,
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'available_delta_kopecks' => $availableDeltaKopecks,
                    'reserved_delta_kopecks' => $reservedDeltaKopecks,
                    'order_id' => $orderId,
                    'context_json' => wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
            );
            if ($written === false) {
                throw new RuntimeException('Unable to write loyalty ledger: ' . $this->wpdb->last_error);
            }

            $entry = $this->findByKey($idempotencyKey);
            if (!$entry instanceof LoyaltyEntry) {
                throw new RuntimeException('Unable to read persisted loyalty ledger entry.');
            }

            $this->wpdb->query('COMMIT');
            return $entry;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    public function balance(int $userId): array
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT available_kopecks, reserved_kopecks FROM {$this->accountTable} WHERE user_id = %d",
            $userId
        ), ARRAY_A);

        return [
            'available_kopecks' => is_array($row) ? (int) $row['available_kopecks'] : 0,
            'reserved_kopecks' => is_array($row) ? (int) $row['reserved_kopecks'] : 0,
        ];
    }

    public function history(int $userId, int $limit = 20, int $offset = 0): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->ledgerTable} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
            $userId,
            max(1, min(100, $limit)),
            max(0, $offset)
        ), ARRAY_A);

        return array_map(fn (array $row): LoyaltyEntry => $this->hydrate($row), is_array($rows) ? $rows : []);
    }

    private function findByKey(string $idempotencyKey): ?LoyaltyEntry
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->ledgerTable} WHERE idempotency_key = %s LIMIT 1",
            $idempotencyKey
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): LoyaltyEntry
    {
        $context = json_decode((string) ($row['context_json'] ?? ''), true);

        return new LoyaltyEntry(
            (int) $row['user_id'],
            (string) $row['idempotency_key'],
            (string) $row['type'],
            (int) $row['available_delta_kopecks'],
            (int) $row['reserved_delta_kopecks'],
            (int) $row['order_id'],
            is_array($context) ? $context : [],
            (string) $row['created_at']
        );
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use DomainException;
use Theobroma\Commerce\Loyalty\LoyaltyEntry;
use Theobroma\Commerce\Loyalty\LoyaltyStore;

final class InMemoryLoyaltyStore implements LoyaltyStore
{
    /** @var array<int,array{available:int,reserved:int}> */
    private array $accounts = [];

    /** @var array<string,LoyaltyEntry> */
    private array $entries = [];

    public function seed(int $userId, int $availableKopecks): void
    {
        $this->accounts[$userId] = ['available' => $availableKopecks, 'reserved' => 0];
    }

    public function available(int $userId): int
    {
        return $this->accounts[$userId]['available'] ?? 0;
    }

    public function reserved(int $userId): int
    {
        return $this->accounts[$userId]['reserved'] ?? 0;
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
        if (isset($this->entries[$idempotencyKey])) {
            return $this->entries[$idempotencyKey];
        }

        $account = $this->accounts[$userId] ?? ['available' => 0, 'reserved' => 0];
        $available = $account['available'] + $availableDeltaKopecks;
        $reserved = $account['reserved'] + $reservedDeltaKopecks;
        if (($available < 0 && $type !== 'reverse-accrual') || $reserved < 0) {
            throw new DomainException('Insufficient loyalty balance for this operation.');
        }

        $entry = new LoyaltyEntry(
            $userId,
            $idempotencyKey,
            $type,
            $availableDeltaKopecks,
            $reservedDeltaKopecks,
            $orderId,
            $context,
            '2026-08-06 12:00:00'
        );
        $this->accounts[$userId] = ['available' => $available, 'reserved' => $reserved];
        $this->entries[$idempotencyKey] = $entry;

        return $entry;
    }

    public function balance(int $userId): array
    {
        return [
            'available_kopecks' => $this->available($userId),
            'reserved_kopecks' => $this->reserved($userId),
        ];
    }

    public function history(int $userId, int $limit = 20, int $offset = 0): array
    {
        $entries = array_values(array_filter(
            $this->entries,
            static fn (LoyaltyEntry $entry): bool => $entry->userId === $userId
        ));

        return array_slice($entries, $offset, $limit);
    }
}

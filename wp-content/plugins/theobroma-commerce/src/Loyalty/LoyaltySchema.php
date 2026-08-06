<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class LoyaltySchema
{
    public function __construct(private readonly string $prefix)
    {
    }

    /** @return list<string> */
    public function statements(string $charsetCollate = ''): array
    {
        $charset = trim($charsetCollate);
        if ($charset !== '' && !str_starts_with(strtoupper($charset), 'DEFAULT CHARACTER SET')) {
            $charset = 'DEFAULT CHARACTER SET utf8mb4 COLLATE ' . $charset;
        }
        $suffix = ' ENGINE=InnoDB' . ($charset !== '' ? ' ' . $charset : '');

        return [
            "CREATE TABLE {$this->prefix}theobroma_loyalty_accounts (
                user_id bigint(20) unsigned NOT NULL,
                available_kopecks bigint(20) NOT NULL DEFAULT 0,
                reserved_kopecks bigint(20) NOT NULL DEFAULT 0,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (user_id)
            ){$suffix};",
            "CREATE TABLE {$this->prefix}theobroma_loyalty_ledger (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                idempotency_key varchar(191) NOT NULL,
                type varchar(32) NOT NULL,
                available_delta_kopecks bigint(20) NOT NULL DEFAULT 0,
                reserved_delta_kopecks bigint(20) NOT NULL DEFAULT 0,
                order_id bigint(20) unsigned NOT NULL DEFAULT 0,
                context_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idempotency_key (idempotency_key),
                KEY user_created (user_id,created_at),
                KEY order_id (order_id)
            ){$suffix};",
        ];
    }
}

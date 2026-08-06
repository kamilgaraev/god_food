<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Loyalty\LoyaltySchema;

final class LoyaltySchemaTest extends TestCase
{
    public function testSchemaDefinesAtomicAccountAndIdempotentLedgerTables(): void
    {
        $statements = (new LoyaltySchema('wp_'))->statements('utf8mb4_unicode_ci');

        $this->assertSame(2, count($statements));
        $this->assertTrue(str_contains($statements[0], 'CREATE TABLE wp_theobroma_loyalty_accounts'));
        $this->assertTrue(str_contains($statements[0], 'user_id bigint(20) unsigned NOT NULL'));
        $this->assertTrue(str_contains($statements[0], 'available_kopecks bigint(20) NOT NULL DEFAULT 0'));
        $this->assertTrue(str_contains($statements[0], 'reserved_kopecks bigint(20) NOT NULL DEFAULT 0'));
        $this->assertTrue(str_contains($statements[0], 'PRIMARY KEY  (user_id)'));

        $this->assertTrue(str_contains($statements[1], 'CREATE TABLE wp_theobroma_loyalty_ledger'));
        $this->assertTrue(str_contains($statements[1], 'idempotency_key varchar(191) NOT NULL'));
        $this->assertTrue(str_contains($statements[1], 'UNIQUE KEY idempotency_key (idempotency_key)'));
        $this->assertTrue(str_contains($statements[1], 'KEY user_created (user_id,created_at)'));
        $this->assertTrue(str_contains($statements[1], 'KEY order_id (order_id)'));
        $this->assertTrue(str_contains($statements[1], 'ENGINE=InnoDB'));
    }
}

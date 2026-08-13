<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

use Theobroma\OneC\Orders\OrderFingerprint;

final class OrderFingerprintTest
{
    public function testServiceMetadataDoesNotChangeBusinessFingerprint(): void
    {
        $before = ['status' => 'processing', 'total' => '1200.00', 'items' => [['id' => 10, 'qty' => 2]]];
        $after = $before + ['_theobroma_1c_ack_revision' => 3, '_theobroma_1c_exported_at' => '2026-08-13T10:00:00Z'];

        $this->same(OrderFingerprint::hash($before), OrderFingerprint::hash($after));
    }

    public function testExportedBusinessChangeProducesNewFingerprint(): void
    {
        $before = ['status' => 'processing', 'total' => '1200.00'];
        $after = ['status' => 'refunded', 'total' => '1200.00'];

        $this->same(false, hash_equals(OrderFingerprint::hash($before), OrderFingerprint::hash($after)));
    }

    private function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}

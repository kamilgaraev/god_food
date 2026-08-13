<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

use Theobroma\OneC\Settings\ExchangeOptions;

final class ExchangeOptionsTest
{
    public function testDefaultsExportOrdersAndDisableEveryIncomingDirection(): void
    {
        $options = ExchangeOptions::fromArray([]);
        $this->same(true, $options->exportOrders);
        $this->same(false, $options->importOrderStatuses);
        $this->same(false, $options->importStock);
        $this->same(false, $options->importPrices);
        $this->same(10, $options->uploadLimitMb);
    }

    public function testNormalizesBooleansAndBoundsLimits(): void
    {
        $options = ExchangeOptions::fromArray(['export_orders' => '0', 'import_stock' => '1', 'upload_limit_mb' => 999]);
        $this->same(false, $options->exportOrders);
        $this->same(true, $options->importStock);
        $this->same(20, $options->uploadLimitMb);
    }

    private function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

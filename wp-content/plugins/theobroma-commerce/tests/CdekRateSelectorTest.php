<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Shipping\CdekRateSelector;

final class CdekRateSelectorTest extends TestCase
{
    public function testUsesOnlyTariffsCompatibleWithDoorOrigin(): void
    {
        $rates = [
            ['tariff_code' => 358, 'tariff_name' => 'Фулфилмент. Выдача склад-склад', 'delivery_mode' => 4, 'delivery_sum' => 100],
            ['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => 295],
            ['tariff_code' => 138, 'delivery_mode' => 2, 'delivery_sum' => 545],
            ['tariff_code' => 137, 'delivery_mode' => 3, 'delivery_sum' => 545],
            ['tariff_code' => 139, 'delivery_mode' => 1, 'delivery_sum' => 795],
        ];
        $selector = new CdekRateSelector();

        $this->assertSame(138, $selector->cheapest($rates, 'pickup')['tariff_code']);
        $this->assertSame(139, $selector->cheapest($rates, 'courier')['tariff_code']);
    }
    public function testUsesWarehouseOriginWhenShipmentPointIsConfigured(): void
    {
        $rates = [
            ['tariff_code' => 358, 'tariff_name' => 'Фулфилмент. Выдача склад-склад', 'delivery_mode' => 4, 'delivery_sum' => 100],
            ['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => 295],
            ['tariff_code' => 138, 'delivery_mode' => 2, 'delivery_sum' => 200],
            ['tariff_code' => 137, 'delivery_mode' => 3, 'delivery_sum' => 545],
            ['tariff_code' => 139, 'delivery_mode' => 1, 'delivery_sum' => 300],
        ];
        $selector = new CdekRateSelector();
        $this->assertSame(136, $selector->cheapest($rates, 'pickup', 'MSK362')['tariff_code']);
        $this->assertSame(137, $selector->cheapest($rates, 'courier', 'MSK362')['tariff_code']);
        $this->assertSame(null, $selector->cheapest(array_values(array_filter($rates, static fn($r) => in_array($r['delivery_mode'], [1, 2], true))), 'courier', 'MSK362'));
    }
}

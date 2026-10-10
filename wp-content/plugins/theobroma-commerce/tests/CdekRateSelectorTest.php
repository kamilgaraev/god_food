<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Shipping\CdekRateSelector;

final class CdekRateSelectorTest extends TestCase
{
    public function testUsesOnlyTariffsCompatibleWithDoorOrigin(): void
    {
        $rates = [
            ['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => 295],
            ['tariff_code' => 138, 'delivery_mode' => 2, 'delivery_sum' => 545],
            ['tariff_code' => 137, 'delivery_mode' => 3, 'delivery_sum' => 545],
            ['tariff_code' => 139, 'delivery_mode' => 1, 'delivery_sum' => 795],
        ];
        $selector = new CdekRateSelector();

        $this->assertSame(138, $selector->cheapest($rates, 'pickup')['tariff_code']);
        $this->assertSame(139, $selector->cheapest($rates, 'courier')['tariff_code']);
    }
}

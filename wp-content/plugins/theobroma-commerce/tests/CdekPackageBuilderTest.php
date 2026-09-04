<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Shipping\CdekPackageBuilder;
use Theobroma\Commerce\Shipping\CdekRateSelector;

final class CdekPackageBuilderTest extends TestCase
{
    public function testBuildsCdekPayloadFromRealWeightsAndDimensions(): void
    {
        $builder = new CdekPackageBuilder(44);
        $payload = $builder->build(
            ['postal_code' => '420111', 'city' => 'Казань', 'address' => 'Баумана, 1'],
            [
                ['quantity' => 2, 'weight_kg' => 0.2, 'length_cm' => 20, 'width_cm' => 10, 'height_cm' => 2],
                ['quantity' => 1, 'weight_kg' => 0.1, 'length_cm' => 15, 'width_cm' => 8, 'height_cm' => 3],
            ]
        );

        $this->assertSame(44, $payload['from_location']['code']);
        $this->assertSame(500, $payload['packages'][0]['weight']);
        $this->assertSame(20, $payload['packages'][0]['length']);
        $this->assertSame(10, $payload['packages'][0]['width']);
        $this->assertSame(7, $payload['packages'][0]['height']);
    }

    public function testRejectsPackageWithMissingWeight(): void
    {
        $builder = new CdekPackageBuilder(44);
        $this->assertThrows(
            static fn () => $builder->build(['postal_code' => '420111'], [['quantity' => 1, 'weight_kg' => 0]]),
            \InvalidArgumentException::class
        );
    }

    public function testSelectsCheapestPickupAndCourierTariffs(): void
    {
        $selector = new CdekRateSelector();
        $rates = [
            ['tariff_code' => 138, 'tariff_name' => 'Посылка дверь-склад', 'delivery_mode' => 2, 'delivery_sum' => 450.0, 'period_min' => 2, 'period_max' => 3],
            ['tariff_code' => 235, 'tariff_name' => 'Эконом дверь-склад', 'delivery_mode' => 2, 'delivery_sum' => 390.0, 'period_min' => 4, 'period_max' => 6],
            ['tariff_code' => 139, 'tariff_name' => 'Посылка дверь-дверь', 'delivery_mode' => 1, 'delivery_sum' => 610.0, 'period_min' => 2, 'period_max' => 4],
        ];

        $this->assertSame(235, $selector->cheapest($rates, 'pickup')['tariff_code']);
        $this->assertSame(139, $selector->cheapest($rates, 'courier')['tariff_code']);
    }
}

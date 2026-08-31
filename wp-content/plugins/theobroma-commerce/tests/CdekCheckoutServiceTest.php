<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\CdekCheckoutService;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Tests\Fakes\MemoryTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class CdekCheckoutServiceTest extends TestCase
{
    public function testReturnsNormalizedPickupPointsForResolvedCity(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('token', time() + 3600);
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [['code' => 44, 'city' => 'Москва']]],
            ['status' => 200, 'body' => [[
                'code' => 'MSK1',
                'name' => 'ПВЗ СДЭК',
                'work_time' => '10:00–20:00',
                'location' => ['address_full' => 'Москва, Арбат, 1', 'latitude' => 55.75, 'longitude' => 37.59],
            ]]],
        ]);
        $service = new CdekCheckoutService(new CdekClient($transport, $tokens, 'id', 'secret'));

        $points = $service->points('Москва');

        $this->assertSame('MSK1', $points[0]['id']);
        $this->assertSame('Москва, Арбат, 1', $points[0]['address']);
        $this->assertSame(37.59, $points[0]['longitude']);
    }

    public function testReturnsCheapestConfirmedRateForRequestedKind(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('token', time() + 3600);
        $transport = new RecordingTransport([['status' => 200, 'body' => ['tariff_codes' => [
            ['tariff_code' => 136, 'delivery_mode' => 2, 'delivery_sum' => 490, 'period_min' => 2, 'period_max' => 3],
            ['tariff_code' => 137, 'delivery_mode' => 2, 'delivery_sum' => 350, 'period_min' => 3, 'period_max' => 4],
        ]]]]);
        $service = new CdekCheckoutService(new CdekClient($transport, $tokens, 'id', 'secret'));

        $quote = $service->quote(['type' => 1, 'from_location' => ['code' => 1], 'to_location' => ['city' => 'Москва'], 'packages' => [['weight' => 100]]], 'pickup');

        $this->assertSame(350.0, $quote->cost());
        $this->assertSame(137, $quote->createPayload()['tariff_code']);
        $this->assertSame('pickup', $quote->createPayload()['delivery_kind']);
    }
}

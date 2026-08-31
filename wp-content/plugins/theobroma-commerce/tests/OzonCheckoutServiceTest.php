<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\OzonCheckoutService;
use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;
use Theobroma\Commerce\Tests\Fakes\StaticAccessTokenProvider;

final class OzonCheckoutServiceTest extends TestCase
{
    public function testNormalizesPickupPointsAndLoadsSelectedPointDetails(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['result' => ['points' => [[
                'map_point_id' => 125,
                'coordinate' => ['lat' => 55.75, 'long' => 37.61],
            ]]]]],
            ['status' => 200, 'body' => ['result' => [
                'map_point_id' => 125,
                'name' => 'Ozon ПВЗ',
                'address' => 'Москва, Тверская, 1',
                'work_time' => '09:00–21:00',
                'coordinate' => ['lat' => 55.75, 'long' => 37.61],
            ]]],
        ]);
        $service = new OzonCheckoutService(new OzonClient($transport, new StaticAccessTokenProvider(['token'])));

        $points = $service->points([]);
        $point = $service->point('125');

        $this->assertSame('125', $points[0]['id']);
        $this->assertSame(55.75, $points[0]['latitude']);
        $this->assertSame('Москва, Тверская, 1', $point['address']);
        $this->assertSame('09:00–21:00', $point['work_time']);
    }

    public function testBuildsConfirmedPickupQuoteAndExactOrderPayload(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['result' => ['available' => true]]],
            ['status' => 200, 'body' => ['result' => ['splits' => [[
                'delivery_schema' => 'FBO',
                'warehouse_id' => 777,
                'commissions' => ['total' => ['amount' => '349.50', 'currency' => 'RUB']],
                'delivery_method' => [
                    'id' => 125,
                    'name' => 'Ozon ПВЗ',
                    'delivery_type' => 'PVZ',
                    'timeslots' => [[
                        'timeslot_id' => 991,
                        'client_date_range' => ['from' => '2026-09-02T10:00:00Z', 'to' => '2026-09-02T20:00:00Z'],
                        'logistic_date_range' => ['from' => '2026-09-02T10:00:00Z', 'to' => '2026-09-02T20:00:00Z'],
                    ]],
                ],
                'items' => [['offer_id' => 'CHOCO', 'quantity' => 2, 'sku' => 100500]],
            ]]]]],
        ]);
        $service = new OzonCheckoutService(new OzonClient($transport, new StaticAccessTokenProvider(['token'])));

        $quote = $service->quote(
            ['first_name' => 'Иван', 'last_name' => 'Иванов', 'phone' => '+79990000000'],
            ['pick_up' => ['map_point_id' => 125]],
            [['offer_id' => 'CHOCO', 'quantity' => 2, 'sku' => 100500]],
            ['first_name' => 'Иван', 'last_name' => 'Иванов', 'phone' => '+79990000000']
        );

        $this->assertSame(349.5, $quote->cost());
        $this->assertSame('FBO', $quote->createPayload()['delivery_schema']);
        $this->assertSame(991, $quote->createPayload()['splits'][0]['delivery_method']['timeslot_id']);
        $this->assertSame(125, $quote->createPayload()['delivery']['pick_up']['map_point_id']);
        $this->assertSame('/v1/delivery/check', parse_url($transport->requests[0]['url'], PHP_URL_PATH));
        $this->assertSame('/v2/delivery/checkout', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
    }
}

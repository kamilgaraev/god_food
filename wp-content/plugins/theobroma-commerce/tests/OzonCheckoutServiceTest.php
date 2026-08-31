<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\OzonCheckoutService;
use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;
use Theobroma\Commerce\Tests\Fakes\StaticAccessTokenProvider;

final class OzonCheckoutServiceTest extends TestCase
{
    public function testLoadsDetailedPickupPointsForMapViewport(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['result' => ['clusters' => [[
                'map_point_ids' => ['125'],
                'points_count' => 1,
            ]]]]],
            ['status' => 200, 'body' => ['result' => ['points' => [[
                'enabled' => true,
                'delivery_method' => [
                    'map_point_id' => 125,
                    'name' => 'Ozon ПВЗ',
                    'address' => 'Казань, проспект Космонавтов, 42А',
                    'working_hours' => [
                        ['date' => '2026-08-31T00:00:00Z', 'periods' => [[
                            'min' => ['hours' => 9, 'minutes' => 0],
                            'max' => ['hours' => 21, 'minutes' => 0],
                        ]]],
                        ['date' => '2026-09-01T00:00:00Z', 'periods' => [[
                            'min' => ['hours' => 9, 'minutes' => 0],
                            'max' => ['hours' => 21, 'minutes' => 0],
                        ]]],
                    ],
                    'coordinates' => ['lat' => 55.79, 'long' => 49.20],
                ],
            ]]]]],
        ]);
        $service = new OzonCheckoutService(new OzonClient($transport, new StaticAccessTokenProvider(['token'])));

        $points = $service->points([
            'left_bottom' => ['lat' => 55.70, 'long' => 49.05],
            'right_top' => ['lat' => 55.90, 'long' => 49.30],
        ]);

        $this->assertSame('125', $points[0]['id']);
        $this->assertSame('Казань, проспект Космонавтов, 42А', $points[0]['address']);
        $this->assertSame(55.79, $points[0]['latitude']);
        $this->assertSame('Ежедневно 09:00–21:00', $points[0]['work_time']);
        $this->assertSame('/v1/delivery/map', parse_url($transport->requests[0]['url'], PHP_URL_PATH));
        $this->assertSame(14, $transport->requests[0]['options']['json']['zoom']);
        $this->assertSame('/v1/delivery/point/info', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
        $this->assertSame(['125'], $transport->requests[1]['options']['json']['map_point_ids']);
    }

    public function testLoadsSelectedPointUsingDocumentedBatchPayload(): void
    {
        $transport = new RecordingTransport([[
            'status' => 200,
            'body' => ['result' => ['points' => [[
                'enabled' => true,
                'delivery_method' => [
                    'map_point_id' => 125,
                    'name' => 'Ozon ПВЗ',
                    'address' => 'Казань, проспект Космонавтов, 42А',
                    'coordinates' => ['lat' => 55.79, 'long' => 49.20],
                ],
            ]]]],
        ]]);
        $service = new OzonCheckoutService(new OzonClient($transport, new StaticAccessTokenProvider(['token'])));

        $point = $service->point('125');

        $this->assertSame('125', $point['id']);
        $this->assertSame('Казань, проспект Космонавтов, 42А', $point['address']);
        $this->assertSame(['125'], $transport->requests[0]['options']['json']['map_point_ids']);
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

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonClientTest extends TestCase
{
    public function testUsesPrivateApplicationBearerTokenForDeliveryMethods(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['result' => ['available' => true]]],
            ['status' => 200, 'body' => ['result' => ['points' => [['id' => 10]]]]],
            ['status' => 200, 'body' => ['result' => ['available' => true, 'postings' => []]]],
        ]);
        $client = new OzonClient($transport, 'private-oauth-token');

        $client->deliveryCheck(['phone' => '+79990000000']);
        $client->deliveryPointList(['limit' => 100]);
        $client->deliveryCheckout(['delivery_method' => 'pickup', 'products' => []]);

        $this->assertSame('/v1/delivery/check', parse_url($transport->requests[0]['url'], PHP_URL_PATH));
        $this->assertSame('/v1/delivery/point/list', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
        $this->assertSame('/v2/delivery/checkout', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
        foreach ($transport->requests as $request) {
            $this->assertSame('POST', $request['method']);
            $this->assertSame('Bearer private-oauth-token', $request['options']['headers']['Authorization']);
        }
    }

    public function testSupportsMapPointInfoAndPaidOrderEndpoints(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['result' => ['map_url' => 'https://example.test/map']]],
            ['status' => 200, 'body' => ['result' => ['id' => 10]]],
            ['status' => 200, 'body' => ['result' => ['order_id' => 77, 'postings' => [['posting_number' => 'P1']]]]],
        ]);
        $client = new OzonClient($transport, 'private-oauth-token');

        $map = $client->deliveryMap(['phone' => '+79990000000']);
        $point = $client->deliveryPointInfo(['id' => 10]);
        $order = $client->createOrder(['external_order_id' => 'WC-42']);

        $this->assertSame('https://example.test/map', $map['map_url']);
        $this->assertSame(10, $point['id']);
        $this->assertSame(77, $order['order_id']);
    }

    public function testSupportsDocumentedCancellationTrackingReturnsAndStocksEndpoints(): void
    {
        $responses = array_fill(0, 16, ['status' => 200, 'body' => ['result' => ['ok' => true]]]);
        $transport = new RecordingTransport($responses);
        $client = new OzonClient($transport, 'private-oauth-token');

        $client->cancelReasons([]);
        $client->cancelReasonsByOrder(['order_id' => 77]);
        $client->cancelReasonsByPosting(['posting_number' => 'P1']);
        $client->cancelCheck(['order_id' => 77]);
        $client->cancelOrder(['order_id' => 77]);
        $client->cancelPosting(['posting_number' => 'P1']);
        $client->cancelStatus(['order_id' => 77]);
        $client->cancelPostingStatus(['posting_number' => 'P1']);
        $client->returnsList([]);
        $client->analyticsStocks([]);
        $client->productStocks([]);
        $client->fboPostingList([]);
        $client->fboPostingGet([]);
        $client->fbsPostingList([]);
        $client->fbsPostingGet([]);
        $client->postingMarks([]);

        $paths = array_map(static fn (array $request): string => (string) parse_url($request['url'], PHP_URL_PATH), $transport->requests);
        $this->assertSame([
            '/v1/cancel-reason/list',
            '/v1/cancel-reason/list-by-order',
            '/v1/cancel-reason/list-by-posting',
            '/v1/order/cancel/check',
            '/v1/order/cancel',
            '/v1/posting/cancel',
            '/v1/order/cancel/status',
            '/v1/posting/cancel/status',
            '/v1/returns/list',
            '/v1/analytics/stocks',
            '/v4/product/info/stocks',
            '/v2/posting/fbo/list',
            '/v2/posting/fbo/get',
            '/v3/posting/fbs/list',
            '/v3/posting/fbs/get',
            '/v1/posting/marks',
        ], $paths);
    }
}

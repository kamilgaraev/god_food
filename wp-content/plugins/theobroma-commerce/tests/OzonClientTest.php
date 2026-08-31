<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Support\ProviderException;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;
use Theobroma\Commerce\Tests\Fakes\StaticAccessTokenProvider;

final class OzonClientTest extends TestCase
{
    public function testKeepsRedactedProviderErrorForDiagnostics(): void
    {
        $transport = new RecordingTransport([[
            'status' => 403,
            'body' => ['message' => 'forbidden', 'client_secret' => 'do-not-log'],
        ]]);
        $client = new OzonClient($transport, new StaticAccessTokenProvider(['token']));

        try {
            $client->deliveryPointList([]);
            $this->assertTrue(false, 'Expected provider exception.');
        } catch (ProviderException $exception) {
            $this->assertSame(403, $exception->statusCode());
            $this->assertSame('forbidden', $exception->context()['response']['message']);
            $this->assertSame('[redacted]', $exception->context()['response']['client_secret']);
        }
    }

    public function testUsesPrivateApplicationBearerTokenForDeliveryMethods(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['is_possible' => true]],
            ['status' => 200, 'body' => ['points' => [['id' => 10]]]],
            ['status' => 200, 'body' => ['delivery_schema' => 'MIX', 'splits' => []]],
        ]);
        $tokens = new StaticAccessTokenProvider(['private-oauth-token']);
        $client = new OzonClient($transport, $tokens);

        $client->deliveryCheck(['client_phone' => '79990000000']);
        $client->deliveryPointList(['limit' => 100]);
        $client->deliveryCheckout(['delivery_method' => 'pickup', 'products' => []]);

        $this->assertSame('/v1/delivery/check', parse_url($transport->requests[0]['url'], PHP_URL_PATH));
        $this->assertSame('/v1/delivery/point/list', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
        $this->assertSame('/v2/delivery/checkout', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
        foreach ($transport->requests as $request) {
            $this->assertSame('POST', $request['method']);
            $this->assertSame('Bearer private-oauth-token', $request['options']['headers']['Authorization']);
        }
        $this->assertSame(3, $tokens->tokenCalls);
    }

    public function testSupportsMapPointInfoAndPaidOrderEndpoints(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['map_url' => 'https://example.test/map']],
            ['status' => 200, 'body' => ['id' => 10]],
            ['status' => 200, 'body' => ['order_number' => 'OZ-77', 'postings' => ['P1']]],
        ]);
        $client = new OzonClient($transport, new StaticAccessTokenProvider(['private-oauth-token']));

        $map = $client->deliveryMap(['client_phone' => '79990000000']);
        $point = $client->deliveryPointInfo(['id' => 10]);
        $order = $client->createOrder(['external_order_id' => 'WC-42']);

        $this->assertSame('https://example.test/map', $map['map_url']);
        $this->assertSame(10, $point['id']);
        $this->assertSame('OZ-77', $order['order_number']);
    }

    public function testSupportsDocumentedCancellationTrackingReturnsAndStocksEndpoints(): void
    {
        $responses = array_fill(0, 16, ['status' => 200, 'body' => ['ok' => true]]);
        $transport = new RecordingTransport($responses);
        $client = new OzonClient($transport, new StaticAccessTokenProvider(['private-oauth-token']));

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

    public function testRefreshesTokenAndRetriesOnceAfterUnauthorizedResponse(): void
    {
        $transport = new RecordingTransport([
            ['status' => 401, 'body' => ['message' => 'expired']],
            ['status' => 200, 'body' => ['is_possible' => true]],
        ]);
        $tokens = new StaticAccessTokenProvider(['expired-token', 'fresh-token']);
        $client = new OzonClient($transport, $tokens);

        $result = $client->deliveryCheck(['client_phone' => '79990000000']);

        $this->assertSame(true, $result['is_possible']);
        $this->assertSame(1, $tokens->forgetCalls);
        $this->assertSame(2, $tokens->tokenCalls);
        $this->assertSame('Bearer expired-token', $transport->requests[0]['options']['headers']['Authorization']);
        $this->assertSame('Bearer fresh-token', $transport->requests[1]['options']['headers']['Authorization']);
    }

    public function testDoesNotRetryUnauthorizedResponseTwice(): void
    {
        $transport = new RecordingTransport([
            ['status' => 401, 'body' => ['message' => 'expired']],
            ['status' => 401, 'body' => ['message' => 'denied']],
        ]);
        $tokens = new StaticAccessTokenProvider(['expired-token', 'rejected-token']);
        $client = new OzonClient($transport, $tokens);

        $exception = $this->assertThrows(
            static fn (): array => $client->deliveryCheck(['client_phone' => '79990000000']),
            ProviderException::class
        );

        $this->assertSame(2, count($transport->requests));
        $this->assertSame(1, $tokens->forgetCalls);
        $this->assertSame(['response' => ['message' => 'denied']], $exception->context());
    }
}

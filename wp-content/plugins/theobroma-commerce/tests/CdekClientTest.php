<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Tests\Fakes\MemoryTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class CdekClientTest extends TestCase
{
    public function testAuthenticatesOnceAndBuildsTariffListRequest(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['access_token' => 'access-1', 'expires_in' => 3600]],
            ['status' => 200, 'body' => ['tariff_codes' => [['tariff_code' => 136, 'delivery_sum' => 450.5]]]],
        ]);
        $client = new CdekClient($transport, new MemoryTokenStore(), 'client-id', 'client-secret', 'https://api.cdek.ru');
        $payload = [
            'type' => 1,
            'from_location' => ['code' => 44],
            'to_location' => ['postal_code' => '420111'],
            'packages' => [['weight' => 500, 'length' => 20, 'width' => 10, 'height' => 5]],
        ];

        $rates = $client->calculateTariffs($payload);

        $this->assertSame(2, count($transport->requests));
        $this->assertSame('POST', $transport->requests[0]['method']);
        $this->assertSame('https://api.cdek.ru/v2/oauth/token', $transport->requests[0]['url']);
        $this->assertSame('client_credentials', $transport->requests[0]['options']['body']['grant_type']);
        $this->assertSame('POST', $transport->requests[1]['method']);
        $this->assertSame('https://api.cdek.ru/v2/calculator/tarifflist', $transport->requests[1]['url']);
        $this->assertSame('Bearer access-1', $transport->requests[1]['options']['headers']['Authorization']);
        $this->assertSame($payload, $transport->requests[1]['options']['json']);
        $this->assertSame(136, $rates[0]['tariff_code']);
    }

    public function testUsesCachedTokenForDeliveryPoints(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('cached-token', time() + 3000);
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [['code' => 'MSK1', 'name' => 'Office']]],
        ]);
        $client = new CdekClient($transport, $tokens, 'client-id', 'client-secret', 'https://api.cdek.ru');

        $points = $client->deliveryPoints(['city_code' => 44, 'type' => 'PVZ']);

        $this->assertSame(1, count($transport->requests));
        $this->assertSame('GET', $transport->requests[0]['method']);
        $this->assertSame('https://api.cdek.ru/v2/deliverypoints', $transport->requests[0]['url']);
        $this->assertSame(['city_code' => 44, 'type' => 'PVZ'], $transport->requests[0]['options']['query']);
        $this->assertSame('MSK1', $points[0]['code']);
    }

    public function testCreatesAndReadsOrderUsingDocumentedEndpoints(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('cached-token', time() + 3000);
        $transport = new RecordingTransport([
            ['status' => 202, 'body' => ['entity' => ['uuid' => 'cdek-uuid']]],
            ['status' => 200, 'body' => ['entity' => ['uuid' => 'cdek-uuid', 'cdek_number' => '123']]],
        ]);
        $client = new CdekClient($transport, $tokens, 'client-id', 'client-secret', 'https://api.cdek.ru');

        $created = $client->createOrder(['number' => 'WC-42']);
        $loaded = $client->getOrder('cdek-uuid');

        $this->assertSame('https://api.cdek.ru/v2/orders', $transport->requests[0]['url']);
        $this->assertSame(['number' => 'WC-42'], $transport->requests[0]['options']['json']);
        $this->assertSame('https://api.cdek.ru/v2/orders/cdek-uuid', $transport->requests[1]['url']);
        $this->assertSame('cdek-uuid', $created['uuid']);
        $this->assertSame('123', $loaded['cdek_number']);
    }

    public function testResolvesCdekCityBeforeLoadingPickupPoints(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('cached-token', time() + 3000);
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [['code' => 270, 'city' => 'Казань']]],
        ]);
        $client = new CdekClient($transport, $tokens, 'client-id', 'client-secret', 'https://api.cdek.ru');

        $cities = $client->cities(['city' => 'Казань', 'country_codes' => 'RU']);

        $this->assertSame('/v2/location/cities', parse_url($transport->requests[0]['url'], PHP_URL_PATH));
        $this->assertSame(270, $cities[0]['code']);
    }
}

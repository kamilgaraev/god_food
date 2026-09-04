<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\CdekOrderPayloadFactory;

final class CdekOrderPayloadFactoryTest extends TestCase
{
    public function testBuildsPaidWooOrderAsCdekOrderPayload(): void
    {
        $factory = new CdekOrderPayloadFactory(44, 'Москва, ул. Фабричная, 1');
        $payload = $factory->build([
            'number' => '1001',
            'tariff_code' => 136,
            'delivery_kind' => 'pickup',
            'pickup_code' => 'KZN1',
            'recipient' => ['name' => 'Иван Иванов', 'phone' => '+79990000000', 'email' => 'a@example.ru'],
            'destination' => ['city' => 'Казань', 'postal_code' => '420111', 'address' => 'Баумана, 1'],
            'items' => [[
                'sku' => 'CHOCO-1', 'name' => 'Шоколад', 'quantity' => 2,
                'unit_price' => 500.0, 'weight_g' => 200,
            ]],
        ]);

        $this->assertSame(136, $payload['tariff_code']);
        $this->assertSame('KZN1', $payload['delivery_point']);
        $this->assertSame(400, $payload['packages'][0]['weight']);
        $this->assertSame(2, $payload['packages'][0]['items'][0]['amount']);
        $this->assertSame(500.0, $payload['packages'][0]['items'][0]['cost']);
        $this->assertSame(0.0, $payload['packages'][0]['items'][0]['payment']['value']);
    }

    public function testRejectsMissingTariffCode(): void
    {
        $factory = new CdekOrderPayloadFactory(44, 'Москва, ул. Фабричная, 1');

        $this->assertThrows(static fn () => $factory->build([
            'number' => '1002',
            'tariff_code' => 0,
            'delivery_kind' => 'courier',
            'recipient' => ['name' => 'Иван Иванов', 'phone' => '+79990000000'],
            'destination' => ['city' => 'Казань', 'address' => 'Баумана, 1'],
            'items' => [[
                'sku' => 'CHOCO-1', 'name' => 'Шоколад', 'quantity' => 1,
                'unit_price' => 500.0, 'weight_g' => 200,
            ]],
        ]), \InvalidArgumentException::class);
    }

    public function testAddsItemPaymentForCashOnDelivery(): void
    {
        $factory = new CdekOrderPayloadFactory(44, 'Москва, ул. Фабричная, 1');
        $payload = $factory->build([
            'number' => '1003',
            'tariff_code' => 136,
            'delivery_kind' => 'pickup',
            'pickup_code' => 'KZN1',
            'cod' => true,
            'recipient' => ['name' => 'Иван Иванов', 'phone' => '+79990000000'],
            'items' => [[
                'sku' => 'CHOCO-1', 'name' => 'Шоколад', 'quantity' => 1,
                'unit_price' => 500.0, 'weight_g' => 200,
            ]],
        ]);

        $this->assertSame(500.0, $payload['packages'][0]['items'][0]['payment']['value']);
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliverySelection;
use Theobroma\Commerce\Orders\DeliveryOrderMeta;

final class DeliveryOrderMetaTest extends TestCase
{
    public function testBuildsProviderOrderMetaFromConfirmedSelection(): void
    {
        $selection = DeliverySelection::fromArray([
            'provider' => 'ozon',
            'kind' => 'pickup',
            'fingerprint' => 'cart-1',
            'point' => ['id' => '778', 'address' => 'Москва'],
            'quote' => ['cost' => 199, 'label' => 'Ozon ПВЗ'],
            'create_payload' => ['delivery_schema' => 'FBS', 'splits' => [['warehouse_id' => 1]]],
        ]);

        $meta = DeliveryOrderMeta::values($selection);

        $this->assertSame('ozon', $meta['_theobroma_delivery_provider']);
        $this->assertSame('778', $meta['_theobroma_delivery_point']);
        $this->assertSame('778', $meta['_theobroma_ozon_point']);
        $this->assertTrue(str_contains($meta['_theobroma_ozon_create_payload'], 'delivery_schema'));
    }
}

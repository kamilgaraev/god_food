<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliveryRateResolver;
use Theobroma\Commerce\Checkout\DeliverySelection;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;

final class DeliveryRateResolverTest extends TestCase
{
    public function testReturnsBootstrapRateUntilProviderQuoteIsConfirmed(): void
    {
        $memory = [];
        $resolver = new DeliveryRateResolver($this->store($memory));

        $rate = $resolver->resolve('ozon', 'fingerprint-1');

        $this->assertSame('bootstrap', $rate['kind']);
        $this->assertSame('Ozon Доставка', $rate['label']);
        $this->assertSame(0.0, $rate['cost']);
        $this->assertSame(true, $rate['requires_selection']);

        $cdekRate = $resolver->resolve('cdek', 'fingerprint-1');
        $this->assertSame('СДЭК', $cdekRate['label']);
    }

    public function testReturnsOnlyServerConfirmedQuoteForMatchingFingerprint(): void
    {
        $memory = [];
        $store = $this->store($memory);
        $store->save(DeliverySelection::fromArray([
            'provider' => 'cdek',
            'kind' => 'pickup',
            'fingerprint' => 'fingerprint-1',
            'point' => ['id' => 'MSK1', 'address' => 'Москва'],
            'quote' => ['cost' => 350, 'label' => 'СДЭК — пункт выдачи, 3–4 дн.'],
            'create_payload' => ['tariff_code' => 137, 'delivery_kind' => 'pickup'],
        ]));
        $resolver = new DeliveryRateResolver($store);

        $rate = $resolver->resolve('cdek', 'fingerprint-1');

        $this->assertSame('pickup', $rate['kind']);
        $this->assertSame(350.0, $rate['cost']);
        $this->assertSame(137, $rate['meta_data']['theobroma_tariff_code']);
        $this->assertSame('MSK1', $rate['meta_data']['theobroma_pickup_point']);
        $this->assertSame('Москва', $rate['meta_data']['theobroma_pickup_address']);
        $this->assertSame(false, $rate['requires_selection']);
    }

    /** @param array<string,mixed> $memory */
    private function store(array &$memory): DeliverySelectionStore
    {
        return new DeliverySelectionStore(
            static function (string $key) use (&$memory): mixed { return $memory[$key] ?? null; },
            static function (string $key, mixed $value) use (&$memory): void { $memory[$key] = $value; },
            static function (string $key) use (&$memory): void { unset($memory[$key]); }
        );
    }
}

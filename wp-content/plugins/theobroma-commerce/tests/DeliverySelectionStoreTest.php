<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliveryFingerprint;
use Theobroma\Commerce\Checkout\DeliverySelection;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;

final class DeliverySelectionStoreTest extends TestCase
{
    public function testPersistsOnlyNormalizedProviderSelection(): void
    {
        $memory = [];
        $store = $this->store($memory);
        $selection = DeliverySelection::fromArray([
            'provider' => 'OZON<script>',
            'kind' => 'pickup',
            'fingerprint' => 'cart-1',
            'point' => ['id' => ' 9001 ', 'name' => 'ПВЗ', 'address' => 'Москва'],
            'quote' => ['cost' => 349.5, 'label' => 'Ozon — завтра'],
            'create_payload' => ['delivery_schema' => 'MIX', 'splits' => [['warehouse_id' => 7]]],
        ]);

        $store->save($selection);
        $actual = $store->load()?->toArray();

        $this->assertSame('ozon', $actual['provider'] ?? null);
        $this->assertSame('pickup', $actual['kind'] ?? null);
        $this->assertSame('9001', $actual['point']['id'] ?? null);
        $this->assertSame(349.5, $actual['quote']['cost'] ?? null);
        $this->assertSame('MIX', $actual['create_payload']['delivery_schema'] ?? null);
    }

    public function testRejectsConfirmedQuoteAfterCartOrDestinationChanges(): void
    {
        $memory = [];
        $store = $this->store($memory);
        $store->save(DeliverySelection::fromArray([
            'provider' => 'cdek',
            'kind' => 'courier',
            'fingerprint' => 'old-fingerprint',
            'quote' => ['cost' => 500, 'label' => 'СДЭК'],
            'create_payload' => ['tariff_code' => 137],
        ]));

        $this->assertSame(null, $store->confirmedFor('cdek', 'new-fingerprint'));
        $this->assertSame(null, $store->load());
    }

    public function testCheckingAnotherProviderDoesNotEraseConfirmedSelection(): void
    {
        $memory = [];
        $store = $this->store($memory);
        $store->save(DeliverySelection::fromArray([
            'provider' => 'ozon',
            'kind' => 'pickup',
            'fingerprint' => 'cart-1',
            'point' => ['id' => '9001', 'address' => 'Казань'],
            'quote' => ['cost' => 349.5, 'label' => 'Ozon Доставка'],
            'create_payload' => ['delivery_schema' => 'MIX'],
        ]));

        $this->assertSame(null, $store->confirmedFor('cdek', 'cart-1'));
        $this->assertSame('ozon', $store->confirmedFor('ozon', 'cart-1')?->provider());
    }

    public function testReportsWhyAStoredSelectionCannotBeConfirmed(): void
    {
        $memory = [];
        $events = [];
        $store = new DeliverySelectionStore(
            static function (string $key) use (&$memory): mixed { return $memory[$key] ?? null; },
            static function (string $key, mixed $value) use (&$memory): void { $memory[$key] = $value; },
            static function (string $key) use (&$memory): void { unset($memory[$key]); },
            static function (string $message, array $context) use (&$events): void {
                $events[] = [$message, $context];
            }
        );
        $store->save(DeliverySelection::fromArray([
            'provider' => 'ozon',
            'kind' => 'pickup',
            'fingerprint' => 'saved-fingerprint',
            'point' => ['id' => '9001'],
            'quote' => ['cost' => 349.5, 'label' => 'Ozon Доставка'],
            'create_payload' => ['delivery_schema' => 'MIX'],
        ]));

        $store->confirmedFor('ozon', 'requested-fingerprint');

        $this->assertSame('Delivery selection saved', $events[0][0] ?? null);
        $this->assertSame('saved-fingerprint', $events[0][1]['fingerprint'] ?? null);
        $this->assertSame('Delivery selection unavailable', $events[1][0] ?? null);
        $this->assertSame('fingerprint_mismatch', $events[1][1]['reason'] ?? null);
        $this->assertSame('requested-fingerprint', $events[1][1]['requested_fingerprint'] ?? null);
        $this->assertSame('saved-fingerprint', $events[1][1]['saved_fingerprint'] ?? null);
    }

    public function testFingerprintChangesWithQuantityOrDestination(): void
    {
        $base = DeliveryFingerprint::fromData(
            [['product_id' => 10, 'variation_id' => 0, 'quantity' => 1]],
            ['city' => 'Москва', 'postcode' => '101000', 'address' => 'Тверская, 1']
        );
        $quantityChanged = DeliveryFingerprint::fromData(
            [['product_id' => 10, 'variation_id' => 0, 'quantity' => 2]],
            ['city' => 'Москва', 'postcode' => '101000', 'address' => 'Тверская, 1']
        );
        $addressChanged = DeliveryFingerprint::fromData(
            [['product_id' => 10, 'variation_id' => 0, 'quantity' => 1]],
            ['city' => 'Казань', 'postcode' => '420000', 'address' => 'Баумана, 1']
        );

        $this->assertSame(false, $base === $quantityChanged);
        $this->assertSame(false, $base === $addressChanged);
    }

    /** @param array<string,mixed> $memory */
    private function store(array &$memory): DeliverySelectionStore
    {
        return new DeliverySelectionStore(
            static function (string $key) use (&$memory): mixed {
                return $memory[$key] ?? null;
            },
            static function (string $key, mixed $value) use (&$memory): void {
                $memory[$key] = $value;
            },
            static function (string $key) use (&$memory): void {
                unset($memory[$key]);
            }
        );
    }
}

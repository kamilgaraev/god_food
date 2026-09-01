<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\ShippingRateCache;

final class ShippingRateCacheTest extends TestCase
{
    public function testInvalidatesOnlyCachedShippingPackages(): void
    {
        $session = new ShippingRateCacheSessionStub([
            'cart' => ['product' => 29],
            'shipping_for_package_0' => ['package_hash' => 'old-0'],
            'shipping_for_package_1' => ['package_hash' => 'old-1'],
            'chosen_payment_method' => 'yookassa',
        ]);

        (new ShippingRateCache($session))->invalidate();

        $this->assertSame(['product' => 29], $session->data['cart']);
        $this->assertSame(null, $session->data['shipping_for_package_0']);
        $this->assertSame(null, $session->data['shipping_for_package_1']);
        $this->assertSame('yookassa', $session->data['chosen_payment_method']);
    }
}

final class ShippingRateCacheSessionStub
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data) {}

    /** @return array<string,mixed> */
    public function get_session_data(): array { return $this->data; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Plugin;
use Theobroma\Commerce\Shipping\OzonShippingMethod;

final class OzonShippingRegistrationTest extends TestCase
{
    public function testRegistersOzonAsWooCommerceShippingMethod(): void
    {
        $methods = Plugin::shippingMethods([]);

        $this->assertSame(OzonShippingMethod::class, $methods['theobroma_ozon'] ?? null);
    }
}

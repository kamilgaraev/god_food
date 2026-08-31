<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliverySelector;

final class DeliverySelectorTest extends TestCase
{
    public function testLoadsAssetsOnAnyStorefrontPageForModalCheckout(): void
    {
        $selector = new DeliverySelector();

        $this->assertTrue($selector->shouldLoadAssets(false));
        $this->assertSame(false, $selector->shouldLoadAssets(true));
    }
}

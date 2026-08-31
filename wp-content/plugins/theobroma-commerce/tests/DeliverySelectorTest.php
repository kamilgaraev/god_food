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

    public function testReplacesCachedBootstrapRateLabel(): void
    {
        $selector = new DeliverySelector();

        $this->assertSame(
            'Ozon Доставка',
            $selector->bootstrapRateLabel(
                'theobroma_ozon:1',
                ['theobroma_requires_selection' => 'yes'],
                'Ozon Доставка — выбрать способ'
            )
        );
        $this->assertSame(
            'СДЭК',
            $selector->bootstrapRateLabel(
                'theobroma_cdek:2',
                ['theobroma_requires_selection' => 'yes'],
                'СДЭК — выбрать способ'
            )
        );
    }
}

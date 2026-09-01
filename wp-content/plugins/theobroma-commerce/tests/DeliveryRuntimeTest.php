<?php

declare(strict_types=1);

namespace {
    if (!function_exists('WC')) {
        function WC(): object
        {
            return $GLOBALS['theobroma_delivery_runtime_wc'];
        }
    }

    if (!function_exists('wc_load_cart')) {
        function wc_load_cart(): void
        {
            $GLOBALS['theobroma_delivery_runtime_loads']++;
            $GLOBALS['theobroma_delivery_runtime_wc']->cart = $GLOBALS['theobroma_delivery_runtime_loaded_cart'];
        }
    }
}

namespace Theobroma\Commerce\Tests {
    use Theobroma\Commerce\Checkout\DeliveryRuntime;

    final class DeliveryRuntimeTest extends TestCase
    {
        public function testLoadsWooCartInsideRestRequestsBeforeReadingContents(): void
        {
            $item = ['product_id' => 29, 'quantity' => 1];
            $GLOBALS['theobroma_delivery_runtime_loads'] = 0;
            $GLOBALS['theobroma_delivery_runtime_loaded_cart'] = new DeliveryRuntimeCartStub([$item]);
            $GLOBALS['theobroma_delivery_runtime_wc'] = (object) [
                'cart' => null,
                'customer' => null,
            ];

            $package = DeliveryRuntime::currentPackage();

            $this->assertSame(1, $GLOBALS['theobroma_delivery_runtime_loads']);
            $this->assertSame([$item], $package['contents']);
        }
    }

    final class DeliveryRuntimeCartStub
    {
        /** @param array<int,array<string,int>> $contents */
        public function __construct(private array $contents)
        {
        }

        /** @return array<int,array<string,int>> */
        public function get_cart(): array
        {
            return $this->contents;
        }
    }
}

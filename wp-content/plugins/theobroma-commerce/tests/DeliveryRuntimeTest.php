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

    if (!function_exists('wc_get_product')) {
        function wc_get_product(int $id): mixed
        {
            return $GLOBALS['theobroma_delivery_runtime_products'][$id] ?? null;
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

        public function testRestoresItemsDirectlyFromWooSessionWhenLateCartInitializationStaysEmpty(): void
        {
            $product = new \stdClass();
            $GLOBALS['theobroma_delivery_runtime_products'] = [29 => $product];
            $GLOBALS['theobroma_delivery_runtime_loads'] = 0;
            $GLOBALS['theobroma_delivery_runtime_wc'] = (object) [
                'cart' => new DeliveryRuntimeCartStub([]),
                'customer' => null,
                'session' => new DeliveryRuntimeSessionStub([
                    'line-key' => [
                        'product_id' => 29,
                        'variation_id' => 0,
                        'quantity' => 2,
                    ],
                ]),
            ];

            $package = DeliveryRuntime::currentPackage();

            $this->assertSame(0, $GLOBALS['theobroma_delivery_runtime_loads']);
            $this->assertSame(29, $package['contents']['line-key']['product_id']);
            $this->assertSame(2, $package['contents']['line-key']['quantity']);
            $this->assertSame($product, $package['contents']['line-key']['data']);
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

    final class DeliveryRuntimeSessionStub
    {
        /** @param array<string,array<string,int>> $cart */
        public function __construct(private array $cart)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'cart' ? $this->cart : $default;
        }
    }
}

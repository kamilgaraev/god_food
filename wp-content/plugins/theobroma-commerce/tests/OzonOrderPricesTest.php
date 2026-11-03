<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\OzonOrderPrices;
use Theobroma\Commerce\Shipping\FreeShippingPolicy;

require_once __DIR__ . '/Fakes/woocommerce-orders.php';

final class OzonOrderPricesTest extends TestCase
{
    public function testFreeDeliveryKeepsEachOfTwoQuotedSplitPrices(): void
    {
        $order = $this->order(FreeShippingPolicy::customerCost(540.0, 4180.0));
        $payload = $this->payload([
            ['amount' => '430', 'currency' => 'RUB'],
            ['amount' => '110', 'currency' => 'RUB'],
        ]);

        $result = (new OzonOrderPrices())->apply($payload, $order);

        $this->assertSame(OzonOrderPrices::money(430.0, 'RUB'), $result['splits'][0]['delivery_method']['price']);
        $this->assertSame(OzonOrderPrices::money(110.0, 'RUB'), $result['splits'][1]['delivery_method']['price']);
        $this->assertSame(0.0, $order->get_shipping_total());
        $this->assertSame($payload['splits'][0]['delivery_method']['price'], ['amount' => '430', 'currency' => 'RUB']);
        $this->assertSame(OzonOrderPrices::money(2090.0, 'RUB'), $result['splits'][0]['items'][0]['price']);
        $this->assertSame(false, isset($result['splits'][0]['items'][0]['offer_id']));
    }

    public function testSingleSplitUsesQuoteRegardlessOfCustomerShipping(): void
    {
        foreach ([0.0, 99.0] as $customerShipping) {
            $order = $this->order($customerShipping);
            $result = (new OzonOrderPrices())->apply($this->payload([
                ['amount' => '430.25', 'currency' => 'RUB'],
            ]), $order);

            $this->assertSame(OzonOrderPrices::money(430.25, 'RUB'), $result['splits'][0]['delivery_method']['price']);
            $this->assertSame($customerShipping, $order->get_shipping_total());
        }
    }

    public function testPreservesQuotedMoneyIncludingFractionAndProviderFreeDelivery(): void
    {
        $prices = [OzonOrderPrices::money(430.25, 'RUB'), OzonOrderPrices::money(0.0, 'RUB')];
        $result = (new OzonOrderPrices())->apply($this->payload($prices), $this->order(0.0));

        $this->assertSame($prices, array_column(array_column($result['splits'], 'delivery_method'), 'price'));
    }

    public function testMissingQuoteCannotBeReplacedByCustomerShipping(): void
    {
        foreach ([0.0, 540.0] as $customerShipping) {
            $this->assertThrows(
                fn () => (new OzonOrderPrices())->apply($this->payload([[]]), $this->order($customerShipping)),
                \InvalidArgumentException::class
            );
        }
    }

    private function payload(array $prices): array
    {
        return ['splits' => array_map(static fn (array $price): array => [
            'delivery_method' => ['price' => $price],
            'items' => [['sku' => 101, 'offer_id' => 'legacy', 'quantity' => 2 / count($prices)]],
        ], $prices)];
    }

    private function order(float $customerShipping): \WC_Order
    {
        return new class($customerShipping) extends \WC_Order {
            public function __construct(private readonly float $customerShipping) {}
            public function get_currency(): string { return 'RUB'; }
            public function get_shipping_total(): float { return $this->customerShipping; }
            public function get_shipping_tax(): float { return 0.0; }
            public function get_items(string $type = 'line_item'): array
            {
                return [new class {
                    public function get_quantity(): int { return 2; }
                    public function get_total(): float { return 4000.0; }
                    public function get_total_tax(): float { return 180.0; }
                    public function get_product(): object
                    {
                        return new class {
                            public function needs_shipping(): bool { return true; }
                            public function get_meta(string $key, bool $single): string { return '101'; }
                            public function get_parent_id(): int { return 0; }
                        };
                    }
                }];
            }
        };
    }
}

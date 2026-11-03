<?php

declare(strict_types=1);

namespace {
    require_once __DIR__ . '/Fakes/woocommerce-orders.php';
}

namespace Theobroma\Commerce\Tests {
    use Theobroma\Commerce\Loyalty\WooOrderAmounts;

    final class WooOrderAmountsTest extends TestCase
    {
        public function testPaidMerchandiseIncludesLineTaxAndExcludesShipping(): void
        {
            $order = new \WC_Order([
                new LoyaltyLineItem(100.00, 20.00),
                new LoyaltyLineItem(50.00, 10.00),
            ], [new LoyaltyLineItem(500.00, 100.00)]);

            $this->assertSame(18000, (new WooOrderAmounts())->paidMerchandiseKopecks($order));
        }

        public function testRefundedMerchandiseUsesAbsoluteRefundedLineTotals(): void
        {
            $refund = new \WC_Order_Refund([
                new LoyaltyLineItem(-40.00, -8.00),
                new LoyaltyLineItem(-10.00, -2.00),
            ], [new LoyaltyLineItem(-500.00, -100.00)]);

            $this->assertSame(6000, (new WooOrderAmounts())->refundedMerchandiseKopecks($refund));
        }
    }

    final class LoyaltyLineItem
    {
        public function __construct(
            private readonly float $total,
            private readonly float $tax
        ) {
        }

        public function get_total(): float
        {
            return $this->total;
        }

        public function get_total_tax(): float
        {
            return $this->tax;
        }
    }
}

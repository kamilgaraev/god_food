<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Loyalty\LoyaltyCalculator;
use Theobroma\Commerce\Loyalty\LoyaltyCheckout;
use Theobroma\Commerce\Tests\Fakes\InMemoryLoyaltyStore;

final class LoyaltyCheckoutTest extends TestCase
{
    public function testClampsRequestedAmountToTwentyPercentAndBalance(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(41, 30000);
        $checkout = new LoyaltyCheckout($store, new LoyaltyCalculator());

        $this->assertSame(20000, $checkout->acceptedAmount(41, 100000, 50000));
        $this->assertSame(10000, $checkout->acceptedAmount(41, 100000, 10000));

        $store->seed(41, 5000);
        $this->assertSame(5000, $checkout->acceptedAmount(41, 100000, 20000));
    }

    public function testGuestsAndInvalidRequestsCannotRedeemBonuses(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(41, 30000);
        $checkout = new LoyaltyCheckout($store, new LoyaltyCalculator());

        $this->assertSame(0, $checkout->acceptedAmount(0, 100000, 10000));
        $this->assertSame(0, $checkout->acceptedAmount(41, 0, 10000));
        $this->assertSame(0, $checkout->acceptedAmount(41, 100000, -1));
    }
}

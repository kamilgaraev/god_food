<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Shipping\FreeShippingPolicy;

final class FreeShippingPolicyTest extends TestCase
{
    public function testQuotedCostRemainsBelowThresholdAndBecomesZeroAtThreshold(): void
    {
        $this->assertSame(350.0, FreeShippingPolicy::customerCost(350.0, 2999.99));
        $this->assertSame(0.0, FreeShippingPolicy::customerCost(350.0, 3000.0));
        $this->assertSame(0.0, FreeShippingPolicy::customerCost(350.0, 3500.0));
        $this->assertSame(350.0, FreeShippingPolicy::customerCost(350.0, 2500.0));
    }

    public function testThresholdUsesAmountAfterDiscountsAndWithMerchandiseTax(): void
    {
        $this->assertSame(false, FreeShippingPolicy::isEligible(3000.0 - 100.0));
        $this->assertSame(true, FreeShippingPolicy::isEligible(2750.0 + 250.0));
    }

    public function testFreeLabelMatchesTheCustomerPrice(): void
    {
        $this->assertSame('СДЭК', FreeShippingPolicy::customerLabel('СДЭК', 2999.0));
        $this->assertSame('СДЭК — бесплатно', FreeShippingPolicy::customerLabel('СДЭК', 3000.0));
    }
}

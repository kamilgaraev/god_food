<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Loyalty\LoyaltyCalculator;

final class LoyaltyCalculatorTest extends TestCase
{
    public function testAccrualFloorsFivePercentToWholeKopecks(): void
    {
        $calculator = new LoyaltyCalculator();

        $this->assertSame(5000, $calculator->accrual(100001));
        $this->assertSame(0, $calculator->accrual(0));
        $this->assertSame(0, $calculator->accrual(-10000));
    }

    public function testRedemptionNeverExceedsTwentyPercentOrAvailableBalance(): void
    {
        $calculator = new LoyaltyCalculator();

        $this->assertSame(20000, $calculator->redemptionLimit(100000, 50000));
        $this->assertSame(12000, $calculator->redemptionLimit(100000, 12000));
        $this->assertSame(0, $calculator->redemptionLimit(-1, 12000));
        $this->assertSame(0, $calculator->redemptionLimit(100000, -1));
    }
}

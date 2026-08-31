<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\ShipmentDispatchPolicy;

final class ShipmentDispatchPolicyTest extends TestCase
{
    public function testCreatesCodShipmentImmediatelyAfterCheckout(): void
    {
        $policy = new ShipmentDispatchPolicy();
        $this->assertTrue($policy->shouldDispatch('checkout', 'cod', false));
    }

    public function testDefersUnpaidOnlineOrderUntilProcessing(): void
    {
        $policy = new ShipmentDispatchPolicy();
        $this->assertSame(false, $policy->shouldDispatch('checkout', 'stripe', false));
        $this->assertSame(false, $policy->shouldDispatch('processing', 'stripe', false));
        $this->assertTrue($policy->shouldDispatch('processing', 'stripe', true));
    }
}

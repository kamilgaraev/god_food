<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\DeliveryStatusLifecycle as Lifecycle;

final class DeliveryStatusLifecycleTest extends TestCase
{
    public function testCreatedOrderDoesNotMeanHandedToCarrier(): void
    {
        $state = Lifecycle::ozonState(['status' => 'awaiting_packaging', 'substatus' => 'posting_created']);
        $this->assertSame(null, Lifecycle::nextStatus([$state]));
        $this->assertSame(null, Lifecycle::nextStatus(['awaiting_deliver']));
    }

    public function testMapsCarrierDeliveryStages(): void
    {
        foreach (['posting_transferring_to_delivery' => 'shipped', 'posting_in_carriage' => 'in-transit', 'posting_on_way_to_city' => 'in-transit', 'posting_in_pickup_point' => 'pickup-ready', '' => 'delivering'] as $substatus => $expected) {
            $state = Lifecycle::ozonState(['status' => 'delivering', 'substatus' => $substatus]);
            $this->assertSame($expected, $state);
            $this->assertSame($expected, Lifecycle::nextStatus([$state]));
        }
    }

    public function testDoesNotTrustStaleSubstatusOnCancellation(): void
    {
        $state = Lifecycle::ozonState(['status' => 'cancelled', 'substatus' => 'posting_in_pickup_point']);
        $this->assertSame(null, Lifecycle::nextStatus([$state]));
        $this->assertSame(null, Lifecycle::nextStatus(['unknown']));
        $this->assertSame(null, Lifecycle::nextStatus([]));
    }

    public function testWaitsForEveryParcelBeforeCompleting(): void
    {
        $this->assertSame('completed', Lifecycle::nextStatus(['delivered', 'delivered']));
        $this->assertSame('in-transit', Lifecycle::nextStatus(['delivered', 'in-transit']));
        $this->assertSame(null, Lifecycle::nextStatus(['delivered', 'awaiting_packaging']));
        $this->assertSame(null, Lifecycle::nextStatus(['delivered', 'cancelled']));
    }

    public function testDoesNotRevertProgressOrAdminTerminalStatus(): void
    {
        $this->assertSame(true, Lifecycle::canAdvance('processing', 'delivering'));
        $this->assertSame(true, Lifecycle::canAdvance('pickup-ready', 'completed'));
        foreach (['cancelled', 'refunded', 'completed', 'on-hold', 'pending'] as $current) {
            $this->assertSame(false, Lifecycle::canAdvance($current, 'delivering'));
        }
        $this->assertSame(false, Lifecycle::canAdvance('pickup-ready', 'shipped'));
        $this->assertSame(false, Lifecycle::canAdvance('delivering', 'delivering'));
    }

    public function testRegistersDeliveryStagesInOrderStatusList(): void
    {
        $statuses = (new Lifecycle())->statuses(['wc-processing' => 'Processing', 'wc-completed' => 'Completed']);
        foreach (['wc-shipped', 'wc-in-transit', 'wc-delivering', 'wc-pickup-ready'] as $status) {
            $this->assertTrue(isset($statuses[$status]));
        }
    }
}

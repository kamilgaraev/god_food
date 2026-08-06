<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\OzonOrderService;
use Theobroma\Commerce\Tests\Fakes\FakeOzonOrderApi;
use Theobroma\Commerce\Tests\Fakes\MemoryShipmentOrder;

final class OzonOrderServiceTest extends TestCase
{
    public function testCreatesOnlyPaidOrderAndPersistsOrderAndPostingIdentifiers(): void
    {
        $api = new FakeOzonOrderApi(['order_id' => 77, 'postings' => [['posting_number' => 'P1']]]);
        $order = new MemoryShipmentOrder(42);
        $service = new OzonOrderService($api);

        $this->assertSame(null, $service->create($order, false, ['buyer' => []]));
        $result = $service->create($order, true, ['buyer' => []]);

        $this->assertSame(77, $result);
        $this->assertSame(77, $order->get('_theobroma_ozon_order_id'));
        $this->assertSame(['P1'], $order->get('_theobroma_ozon_postings'));
        $this->assertSame(1, count($api->created));
    }

    public function testIsIdempotentAndRejectsAmbiguousSuccessResponse(): void
    {
        $api = new FakeOzonOrderApi(['order_id' => 77]);
        $order = new MemoryShipmentOrder(42);
        $order->set('_theobroma_ozon_order_id', 70);
        $service = new OzonOrderService($api);

        $this->assertSame(70, $service->create($order, true, []));
        $this->assertSame(0, count($api->created));

        $badApi = new FakeOzonOrderApi(['warnings' => ['not created']]);
        $this->assertThrows(
            static fn () => (new OzonOrderService($badApi))->create(new MemoryShipmentOrder(43), true, []),
            \RuntimeException::class
        );
    }
}

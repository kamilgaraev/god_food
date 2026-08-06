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
        $api = new FakeOzonOrderApi(['order_number' => 'OZ-77', 'postings' => ['P1']]);
        $order = new MemoryShipmentOrder(42);
        $service = new OzonOrderService($api);

        $this->assertSame(null, $service->create($order, false, ['buyer' => []]));
        $result = $service->create($order, true, ['buyer' => []]);

        $this->assertSame('OZ-77', $result);
        $this->assertSame('OZ-77', $order->get('_theobroma_ozon_order_number'));
        $this->assertSame(['P1'], $order->get('_theobroma_ozon_postings'));
        $this->assertSame(1, count($api->created));
    }

    public function testIsIdempotentAndRejectsAmbiguousSuccessResponse(): void
    {
        $api = new FakeOzonOrderApi(['order_number' => 'OZ-77']);
        $order = new MemoryShipmentOrder(42);
        $order->set('_theobroma_ozon_order_number', 'OZ-70');
        $service = new OzonOrderService($api);

        $this->assertSame('OZ-70', $service->create($order, true, []));
        $this->assertSame(0, count($api->created));

        $badApi = new FakeOzonOrderApi(['warnings' => ['not created']]);
        $this->assertThrows(
            static fn () => (new OzonOrderService($badApi))->create(new MemoryShipmentOrder(43), true, []),
            \RuntimeException::class
        );
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\CdekShipmentService;
use Theobroma\Commerce\Tests\Fakes\FakeCdekOrderApi;
use Theobroma\Commerce\Tests\Fakes\MemoryShipmentOrder;

final class CdekShipmentServiceTest extends TestCase
{
    public function testCreatesShipmentOnceAndPersistsExternalUuid(): void
    {
        $api = new FakeCdekOrderApi();
        $order = new MemoryShipmentOrder(42);
        $service = new CdekShipmentService($api);

        $first = $service->create($order, ['number' => 'WC-42']);
        $second = $service->create($order, ['number' => 'WC-42']);

        $this->assertSame('cdek-uuid-1', $first);
        $this->assertSame('cdek-uuid-1', $second);
        $this->assertSame(1, $api->createCalls);
        $this->assertSame('cdek-uuid-1', $order->meta['_theobroma_cdek_uuid']);
        $this->assertSame(1, $order->saves);
        $this->assertSame(1, count($order->notes));
    }
}

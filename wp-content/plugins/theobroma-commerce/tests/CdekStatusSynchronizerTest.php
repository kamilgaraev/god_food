<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\CdekStatusSynchronizer;
use Theobroma\Commerce\Tests\Fakes\FakeCdekOrderApi;
use Theobroma\Commerce\Tests\Fakes\MemoryShipmentOrder;

final class CdekStatusSynchronizerTest extends TestCase
{
    public function testRereadsOrderFromCdekAndPersistsLatestStatus(): void
    {
        $api = new FakeCdekOrderApi([
            'uuid' => 'cdek-1',
            'cdek_number' => '123456',
            'statuses' => [
                ['code' => 'CREATED', 'date_time' => '2026-08-06T10:00:00+03:00'],
                ['code' => 'DELIVERED', 'date_time' => '2026-08-07T10:00:00+03:00'],
            ],
        ]);
        $order = new MemoryShipmentOrder();

        $status = (new CdekStatusSynchronizer($api))->sync($order, 'cdek-1');

        $this->assertSame('DELIVERED', $status);
        $this->assertSame('DELIVERED', $order->get('_theobroma_cdek_status'));
        $this->assertSame('123456', $order->get('_theobroma_cdek_number'));
        $this->assertSame(1, $order->saves);
    }

    public function testRejectsProviderResponseForDifferentOrder(): void
    {
        $api = new FakeCdekOrderApi(['uuid' => 'different', 'statuses' => [['code' => 'CREATED']]]);

        $this->assertThrows(
            static fn () => (new CdekStatusSynchronizer($api))->sync(new MemoryShipmentOrder(), 'cdek-1'),
            \RuntimeException::class
        );
    }
}

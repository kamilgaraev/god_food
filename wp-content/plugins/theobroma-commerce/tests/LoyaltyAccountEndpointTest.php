<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Loyalty\LoyaltyAccountEndpoint;
use Theobroma\Commerce\Tests\Fakes\InMemoryLoyaltyStore;

final class LoyaltyAccountEndpointTest extends TestCase
{
    public function testInsertsBonusesBetweenOrdersAndAddresses(): void
    {
        $endpoint = new LoyaltyAccountEndpoint(new InMemoryLoyaltyStore());
        $items = $endpoint->menuItems([
            'dashboard' => 'Главная',
            'orders' => 'Заказы',
            'edit-address' => 'Адреса',
            'customer-logout' => 'Выйти',
        ]);

        $this->assertSame(['dashboard', 'orders', 'bonuses', 'edit-address', 'customer-logout'], array_keys($items));
        $this->assertSame('Бонусы', $items['bonuses']);
        $this->assertSame('bonuses', $endpoint->slug());
    }

    public function testHistoryIsAlwaysScopedToTheRequestedCustomer(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(51, 1000);
        $store->seed(52, 1000);
        $store->mutate(51, 'customer-51', 'accrue', 500, 0, 701);
        $store->mutate(52, 'customer-52', 'accrue', 500, 0, 702);
        $endpoint = new LoyaltyAccountEndpoint($store);

        $history = $endpoint->historyForUser(51, 20, 0);

        $this->assertSame(1, count($history));
        $this->assertSame(51, $history[0]->userId);
        $this->assertSame(701, $history[0]->orderId);
    }
}

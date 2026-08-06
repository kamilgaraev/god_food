<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use DomainException;
use Theobroma\Commerce\Loyalty\LoyaltyService;
use Theobroma\Commerce\Tests\Fakes\InMemoryLoyaltyStore;

final class LoyaltyServiceTest extends TestCase
{
    public function testAccrualIsIdempotentForTheSameOrder(): void
    {
        $store = new InMemoryLoyaltyStore();
        $service = new LoyaltyService($store);

        $first = $service->accrue(7, 101, 5000);
        $second = $service->accrue(7, 101, 5000);

        $this->assertSame(5000, $store->available(7));
        $this->assertSame($first->idempotencyKey, $second->idempotencyKey);
    }

    public function testReservationBecomesSpendWithoutASecondAvailableDebit(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(7, 10000);
        $service = new LoyaltyService($store);

        $service->reserve(7, 102, 8000);
        $this->assertSame(2000, $store->available(7));
        $this->assertSame(8000, $store->reserved(7));

        $service->spend(7, 102, 8000);
        $this->assertSame(2000, $store->available(7));
        $this->assertSame(0, $store->reserved(7));
    }

    public function testReleaseReturnsReservedBonuses(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(8, 10000);
        $service = new LoyaltyService($store);

        $service->reserve(8, 103, 3000);
        $service->release(8, 103, 3000);

        $this->assertSame(10000, $store->available(8));
        $this->assertSame(0, $store->reserved(8));
    }

    public function testReservationCannotExceedAvailableBalance(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(9, 1000);
        $service = new LoyaltyService($store);

        $this->assertThrows(
            static fn () => $service->reserve(9, 104, 1001),
            DomainException::class
        );
    }

    public function testRefundCanReverseAccrualAndRestoreSpentBonusesOnce(): void
    {
        $store = new InMemoryLoyaltyStore();
        $store->seed(10, 10000);
        $service = new LoyaltyService($store);
        $service->reserve(10, 105, 4000);
        $service->spend(10, 105, 4000);
        $service->accrue(10, 105, 5000);

        $service->reverseAccrual(10, 105, 501, 2500);
        $service->restoreSpend(10, 105, 501, 2000);
        $service->reverseAccrual(10, 105, 501, 2500);
        $service->restoreSpend(10, 105, 501, 2000);

        $this->assertSame(10500, $store->available(10));
        $this->assertSame(0, $store->reserved(10));
    }

    public function testRefundRecordsDebtWhenPreviouslyAccruedBonusesWereAlreadySpent(): void
    {
        $store = new InMemoryLoyaltyStore();
        $service = new LoyaltyService($store);
        $service->accrue(11, 106, 5000);
        $service->reserve(11, 107, 5000);
        $service->spend(11, 107, 5000);

        $service->reverseAccrual(11, 106, 502, 5000);

        $this->assertSame(-5000, $store->available(11));
        $this->assertThrows(
            static fn () => $service->reserve(11, 108, 1),
            DomainException::class
        );
    }
}

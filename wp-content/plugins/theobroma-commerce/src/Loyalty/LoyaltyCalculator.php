<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class LoyaltyCalculator
{
    public function accrual(int $paidMerchandiseKopecks): int
    {
        return intdiv(max(0, $paidMerchandiseKopecks) * 5, 100);
    }

    public function redemptionLimit(int $merchandiseKopecks, int $availableKopecks): int
    {
        $twentyPercent = intdiv(max(0, $merchandiseKopecks) * 20, 100);

        return min($twentyPercent, max(0, $availableKopecks));
    }
}

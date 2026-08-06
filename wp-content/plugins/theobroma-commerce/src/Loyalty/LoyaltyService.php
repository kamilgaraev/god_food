<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

use InvalidArgumentException;

final class LoyaltyService
{
    public function __construct(private readonly LoyaltyStore $store)
    {
    }

    public function accrue(int $userId, int $orderId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'accrue', $amountKopecks, 0);
    }

    public function reserve(int $userId, int $orderId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'reserve', -$amountKopecks, $amountKopecks);
    }

    public function spend(int $userId, int $orderId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'spend', 0, -$amountKopecks);
    }

    public function release(int $userId, int $orderId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'release', $amountKopecks, -$amountKopecks);
    }

    public function reverseAccrual(int $userId, int $orderId, int $refundId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'refund:' . $refundId . ':reverse-accrual', -$amountKopecks, 0);
    }

    public function restoreSpend(int $userId, int $orderId, int $refundId, int $amountKopecks): LoyaltyEntry
    {
        return $this->apply($userId, $orderId, $amountKopecks, 'refund:' . $refundId . ':restore-spend', $amountKopecks, 0);
    }

    private function apply(
        int $userId,
        int $orderId,
        int $amountKopecks,
        string $operation,
        int $availableDeltaKopecks,
        int $reservedDeltaKopecks
    ): LoyaltyEntry {
        if ($userId < 1 || $orderId < 1 || $amountKopecks < 1) {
            throw new InvalidArgumentException('Customer, order and loyalty amount must be positive.');
        }

        return $this->store->mutate(
            $userId,
            sprintf('order:%d:%s', $orderId, $operation),
            str_contains($operation, ':') ? substr($operation, strrpos($operation, ':') + 1) : $operation,
            $availableDeltaKopecks,
            $reservedDeltaKopecks,
            $orderId,
            ['amount_kopecks' => $amountKopecks]
        );
    }
}

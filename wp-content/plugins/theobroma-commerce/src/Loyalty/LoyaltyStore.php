<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

interface LoyaltyStore
{
    /** @param array<string,mixed> $context */
    public function mutate(
        int $userId,
        string $idempotencyKey,
        string $type,
        int $availableDeltaKopecks,
        int $reservedDeltaKopecks,
        int $orderId,
        array $context = []
    ): LoyaltyEntry;

    /** @return array{available_kopecks:int,reserved_kopecks:int} */
    public function balance(int $userId): array;

    /** @return list<LoyaltyEntry> */
    public function history(int $userId, int $limit = 20, int $offset = 0): array;
}

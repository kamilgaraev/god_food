<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class LoyaltyEntry
{
    /** @param array<string,mixed> $context */
    public function __construct(
        public readonly int $userId,
        public readonly string $idempotencyKey,
        public readonly string $type,
        public readonly int $availableDeltaKopecks,
        public readonly int $reservedDeltaKopecks,
        public readonly int $orderId,
        public readonly array $context = [],
        public readonly string $createdAt = ''
    ) {
    }

    public function totalDeltaKopecks(): int
    {
        return $this->availableDeltaKopecks + $this->reservedDeltaKopecks;
    }
}

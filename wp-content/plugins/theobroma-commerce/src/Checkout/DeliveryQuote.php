<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryQuote
{
    /** @param array<string,mixed> $createPayload */
    public function __construct(
        private readonly string $provider,
        private readonly string $kind,
        private readonly float $cost,
        private readonly string $label,
        private readonly array $createPayload
    ) {
        if (!in_array($provider, ['ozon', 'cdek'], true) || !in_array($kind, ['pickup', 'courier'], true)) {
            throw new \InvalidArgumentException('Unsupported delivery quote');
        }
        if ($cost < 0 || trim($label) === '' || $createPayload === []) {
            throw new \InvalidArgumentException('Incomplete delivery quote');
        }
    }

    public function provider(): string { return $this->provider; }
    public function kind(): string { return $this->kind; }
    public function cost(): float { return $this->cost; }
    public function label(): string { return $this->label; }

    /** @return array<string,mixed> */
    public function createPayload(): array { return $this->createPayload; }
}

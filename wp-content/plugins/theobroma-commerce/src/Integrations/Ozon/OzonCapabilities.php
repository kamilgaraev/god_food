<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

final class OzonCapabilities
{
    public function __construct(
        private readonly bool $approved,
        private readonly bool $credentialsConfigured,
        private readonly bool $catalogMapped,
        private readonly bool $stocksConfirmed,
        private readonly bool $liveTestCompleted
    ) {
    }

    public function status(): string
    {
        return match (false) {
            $this->approved => 'awaiting_approval',
            $this->credentialsConfigured => 'credentials_missing',
            $this->catalogMapped => 'products_unmapped',
            $this->stocksConfirmed => 'stocks_unconfirmed',
            $this->liveTestCompleted => 'live_test_required',
            default => 'ready',
        };
    }

    public function canOfferDelivery(): bool
    {
        return $this->status() === 'ready';
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Ozon\OAuthStateStore;

final class MemoryOAuthStateStore implements OAuthStateStore
{
    /** @var array<string,int> */
    public array $states = [];

    public function issue(int $userId): string
    {
        $state = 'state-' . $userId . '-' . count($this->states);
        $this->states[$state] = $userId;
        return $state;
    }

    public function consume(string $state): ?int
    {
        if (!isset($this->states[$state])) {
            return null;
        }
        $userId = $this->states[$state];
        unset($this->states[$state]);
        return $userId;
    }
}


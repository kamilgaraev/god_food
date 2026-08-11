<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Ozon\TokenStore;

final class MemoryOzonTokenStore implements TokenStore
{
    /** @var array{token:string,expires_at:int}|null */
    public ?array $value = null;
    public int $forgetCount = 0;

    public function get(): ?array
    {
        return $this->value;
    }

    public function put(string $token, int $expiresAt): void
    {
        $this->value = ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function forget(): void
    {
        $this->forgetCount++;
        $this->value = null;
    }
}

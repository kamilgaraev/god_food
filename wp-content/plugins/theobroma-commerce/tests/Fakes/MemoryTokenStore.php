<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Cdek\TokenStore;

final class MemoryTokenStore implements TokenStore
{
    /** @var array{token:string,expires_at:int}|null */
    public ?array $value = null;

    public function get(): ?array
    {
        return $this->value;
    }

    public function put(string $token, int $expiresAt): void
    {
        $this->value = compact('token', 'expiresAt');
        $this->value = ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function forget(): void
    {
        $this->value = null;
    }
}

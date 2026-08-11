<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Ozon\TokenStore;

final class MemoryOzonTokenStore implements TokenStore
{
    /** @var array{token:string,expires_at:int,refresh_token?:string}|null */
    public ?array $value = null;
    public int $forgetCount = 0;

    public function get(): ?array
    {
        return $this->value;
    }

    public function put(string $token, int $expiresAt, ?string $refreshToken = null): void
    {
        $this->value = ['token' => $token, 'expires_at' => $expiresAt];
        if ($refreshToken !== null && $refreshToken !== '') {
            $this->value['refresh_token'] = $refreshToken;
        }
    }

    public function forget(): void
    {
        $this->forgetCount++;
        $this->value = null;
    }

    public function forgetAccessToken(): void
    {
        $this->forgetCount++;
        $refreshToken = is_array($this->value) ? ($this->value['refresh_token'] ?? null) : null;
        $this->value = is_string($refreshToken) && $refreshToken !== ''
            ? ['token' => '', 'expires_at' => 0, 'refresh_token' => $refreshToken]
            : null;
    }
}

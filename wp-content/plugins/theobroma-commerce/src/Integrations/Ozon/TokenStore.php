<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

interface TokenStore
{
    /** @return array{token:string,expires_at:int,refresh_token?:string}|null */
    public function get(): ?array;

    public function put(string $token, int $expiresAt, ?string $refreshToken = null): void;

    public function forgetAccessToken(): void;

    public function forget(): void;
}

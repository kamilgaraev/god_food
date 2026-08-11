<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

interface TokenStore
{
    /** @return array{token:string,expires_at:int}|null */
    public function get(): ?array;

    public function put(string $token, int $expiresAt): void;

    public function forget(): void;
}

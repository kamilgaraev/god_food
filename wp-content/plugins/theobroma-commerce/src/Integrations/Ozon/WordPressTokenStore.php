<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

final class WordPressTokenStore implements TokenStore
{
    private const KEY = 'theobroma_ozon_access_token';

    public function get(): ?array
    {
        $value = get_transient(self::KEY);
        return is_array($value) && isset($value['token'], $value['expires_at']) ? $value : null;
    }

    public function put(string $token, int $expiresAt): void
    {
        set_transient(self::KEY, ['token' => $token, 'expires_at' => $expiresAt], max(60, $expiresAt - time()));
    }

    public function forget(): void
    {
        delete_transient(self::KEY);
    }
}

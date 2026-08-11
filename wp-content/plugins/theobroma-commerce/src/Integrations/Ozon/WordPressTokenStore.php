<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

final class WordPressTokenStore implements TokenStore
{
    private const KEY = 'theobroma_ozon_oauth_tokens';

    public function get(): ?array
    {
        $value = get_option(self::KEY, null);
        if (!is_array($value) || !isset($value['token'], $value['expires_at'])) {
            return null;
        }

        $tokens = [
            'token' => (string) $value['token'],
            'expires_at' => (int) $value['expires_at'],
        ];
        if (isset($value['refresh_token']) && is_string($value['refresh_token']) && $value['refresh_token'] !== '') {
            $tokens['refresh_token'] = $value['refresh_token'];
        }

        return $tokens;
    }

    public function put(string $token, int $expiresAt, ?string $refreshToken = null): void
    {
        $value = ['token' => $token, 'expires_at' => $expiresAt];
        if ($refreshToken !== null && $refreshToken !== '') {
            $value['refresh_token'] = $refreshToken;
        }
        update_option(self::KEY, $value, false);
    }

    public function forget(): void
    {
        delete_option(self::KEY);
        delete_transient('theobroma_ozon_access_token');
    }

    public function forgetAccessToken(): void
    {
        $tokens = $this->get();
        $refreshToken = is_array($tokens) ? ($tokens['refresh_token'] ?? null) : null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            $this->forget();
            return;
        }

        $this->put('', 0, $refreshToken);
    }
}

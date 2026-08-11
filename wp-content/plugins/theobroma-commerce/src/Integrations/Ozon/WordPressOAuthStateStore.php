<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

final class WordPressOAuthStateStore implements OAuthStateStore
{
    private const PREFIX = 'theobroma_ozon_oauth_state_';

    public function issue(int $userId): string
    {
        $state = bin2hex(random_bytes(32));
        set_transient($this->key($state), $userId, 10 * MINUTE_IN_SECONDS);
        return $state;
    }

    public function consume(string $state): ?int
    {
        if ($state === '') {
            return null;
        }

        $key = $this->key($state);
        $userId = get_transient($key);
        delete_transient($key);

        return is_numeric($userId) && (int) $userId > 0 ? (int) $userId : null;
    }

    private function key(string $state): string
    {
        return self::PREFIX . hash('sha256', $state);
    }
}


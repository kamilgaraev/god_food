<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Infrastructure;

final class SecretRedactor
{
    /** @var list<string> */
    private array $secretKeys = [
        'authorization',
        'api_key',
        'apikey',
        'client_secret',
        'secret',
        'password',
        'token',
        'access_token',
        'refresh_token',
    ];

    /** @param array<mixed> $value
     *  @return array<mixed>
     */
    public function redact(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $this->secretKeys, true)) {
                $result[$key] = '[redacted]';
                continue;
            }

            $result[$key] = is_array($item) ? $this->redact($item) : $item;
        }

        return $result;
    }
}

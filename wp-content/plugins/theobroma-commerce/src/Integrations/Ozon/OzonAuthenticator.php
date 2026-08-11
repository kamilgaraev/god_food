<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

use Theobroma\Commerce\Contracts\Transport;
use Theobroma\Commerce\Support\ProviderException;

final class OzonAuthenticator implements AccessTokenProvider
{
    public function __construct(
        private readonly Transport $transport,
        private readonly TokenStore $tokens,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl = 'https://api-seller.ozon.ru'
    ) {
    }

    public function token(): string
    {
        $cached = $this->tokens->get();
        if ($cached !== null && $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }

        if (trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            throw ProviderException::fromResponse('Ozon credentials are not configured', 0);
        }

        $response = $this->transport->request('POST', rtrim($this->baseUrl, '/') . '/oauth/token', [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);
        $token = $response['body']['access_token'] ?? null;
        if ($response['status'] !== 200 || !is_string($token) || trim($token) === '') {
            $this->tokens->forget();
            throw ProviderException::fromResponse('Ozon authentication failed', $response['status']);
        }

        $expiresIn = max(60, (int) ($response['body']['expires_in'] ?? 3600));
        $this->tokens->put($token, time() + $expiresIn);

        return $token;
    }

    public function forget(): void
    {
        $this->tokens->forget();
    }
}

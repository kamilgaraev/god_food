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
        private readonly string $baseUrl = 'https://xapi.ozon.ru'
    ) {
    }

    public function token(): string
    {
        $cached = $this->tokens->get();
        if ($cached !== null && $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }

        $refreshToken = is_array($cached) ? (string) ($cached['refresh_token'] ?? '') : '';
        if ($refreshToken === '') {
            throw ProviderException::fromResponse('Ozon seller authorization is required', 401);
        }

        $this->assertCredentials();

        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ], $refreshToken, false);
    }

    public function authorize(string $code, string $redirectUri): void
    {
        $this->assertCredentials();
        if (trim($code) === '' || trim($redirectUri) === '') {
            throw ProviderException::fromResponse('Ozon authorization response is incomplete', 400);
        }

        $this->requestToken([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ], null, true);
    }

    public function forget(): void
    {
        $this->tokens->forgetAccessToken();
    }

    /** @param array<string,string> $payload */
    private function requestToken(array $payload, ?string $fallbackRefreshToken, bool $requireRefreshToken): string
    {
        $response = $this->transport->request('POST', rtrim($this->baseUrl, '/') . '/oauth/token', [
            'headers' => ['Accept' => 'application/json'],
            'json' => $payload,
        ]);
        $token = $response['body']['access_token'] ?? null;
        $expiresIn = $response['body']['expires_in'] ?? null;
        $refreshToken = $response['body']['refresh_token'] ?? $fallbackRefreshToken;
        if (
            $response['status'] !== 200
            || !is_string($token)
            || trim($token) === ''
            || !is_numeric($expiresIn)
            || (int) $expiresIn <= 0
            || ($requireRefreshToken && (!is_string($refreshToken) || trim($refreshToken) === ''))
        ) {
            throw ProviderException::fromResponse('Ozon OAuth token exchange failed', $response['status']);
        }

        $refreshToken = is_string($refreshToken) && trim($refreshToken) !== '' ? $refreshToken : null;
        $this->tokens->put($token, time() + (int) $expiresIn, $refreshToken);

        return $token;
    }

    private function assertCredentials(): void
    {
        if (trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            throw ProviderException::fromResponse('Ozon credentials are not configured', 0);
        }
    }
}

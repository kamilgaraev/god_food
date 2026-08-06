<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Cdek;

use Theobroma\Commerce\Contracts\Transport;
use Theobroma\Commerce\Support\ProviderException;

final class CdekClient implements CdekOrderApi
{
    public function __construct(
        private readonly Transport $transport,
        private readonly TokenStore $tokens,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl = 'https://api.cdek.ru'
    ) {
    }

    /** @param array<mixed> $payload
     *  @return list<array<mixed>>
     */
    public function calculateTariffs(array $payload): array
    {
        $body = $this->authorizedRequest('POST', '/v2/calculator/tarifflist', ['json' => $payload]);
        $rates = $body['tariff_codes'] ?? null;
        if (!is_array($rates)) {
            throw ProviderException::fromResponse('CDEK returned an invalid tariff response', 502, ['response' => $body]);
        }

        return array_values(array_filter($rates, 'is_array'));
    }

    /** @param array<mixed> $query
     *  @return list<array<mixed>>
     */
    public function deliveryPoints(array $query): array
    {
        $body = $this->authorizedRequest('GET', '/v2/deliverypoints', ['query' => $query]);
        if (!array_is_list($body)) {
            throw ProviderException::fromResponse('CDEK returned an invalid delivery-points response', 502, ['response' => $body]);
        }

        return array_values(array_filter($body, 'is_array'));
    }

    /** @param array<mixed> $query
     *  @return list<array<mixed>>
     */
    public function cities(array $query): array
    {
        $body = $this->authorizedRequest('GET', '/v2/location/cities', ['query' => $query]);
        if (!array_is_list($body)) {
            throw ProviderException::fromResponse('CDEK returned an invalid cities response', 502, ['response' => $body]);
        }

        return array_values(array_filter($body, 'is_array'));
    }

    /** @param array<mixed> $payload
     *  @return array<mixed>
     */
    public function createOrder(array $payload): array
    {
        $body = $this->authorizedRequest('POST', '/v2/orders', ['json' => $payload], [200, 202]);
        $entity = $body['entity'] ?? null;
        if (!is_array($entity) || empty($entity['uuid'])) {
            throw ProviderException::fromResponse('CDEK did not accept the order', 502, ['response' => $body]);
        }

        return $entity;
    }

    /** @return array<mixed> */
    public function getOrder(string $uuid): array
    {
        $body = $this->authorizedRequest('GET', '/v2/orders/' . rawurlencode($uuid));
        $entity = $body['entity'] ?? null;
        if (!is_array($entity)) {
            throw ProviderException::fromResponse('CDEK returned an invalid order response', 502, ['response' => $body]);
        }

        return $entity;
    }

    /** @param array<mixed> $options
     *  @param list<int> $acceptedStatuses
     *  @return array<mixed>
     */
    private function authorizedRequest(string $method, string $path, array $options = [], array $acceptedStatuses = [200]): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'Accept' => 'application/json',
        ]);
        $response = $this->transport->request($method, rtrim($this->baseUrl, '/') . $path, $options);

        if (!in_array($response['status'], $acceptedStatuses, true)) {
            throw ProviderException::fromResponse('CDEK request failed', $response['status'], ['response' => $response['body']]);
        }

        return $response['body'];
    }

    private function accessToken(): string
    {
        $cached = $this->tokens->get();
        if ($cached !== null && $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }

        $response = $this->transport->request('POST', rtrim($this->baseUrl, '/') . '/v2/oauth/token', [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);
        $token = $response['body']['access_token'] ?? null;
        if ($response['status'] !== 200 || !is_string($token) || $token === '') {
            $this->tokens->forget();
            throw ProviderException::fromResponse('CDEK authentication failed', $response['status'], ['response' => $response['body']]);
        }

        $expiresIn = max(60, (int) ($response['body']['expires_in'] ?? 3600));
        $this->tokens->put($token, time() + $expiresIn);

        return $token;
    }
}

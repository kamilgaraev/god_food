<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Infrastructure;

use Theobroma\Commerce\Contracts\Transport;
use Theobroma\Commerce\Support\ProviderException;

final class WpTransport implements Transport
{
    /** @var callable */
    private $requester;

    public function __construct(?callable $requester = null)
    {
        $this->requester = $requester ?? static fn (string $url, array $args): mixed => wp_remote_request($url, $args);
    }

    public function request(string $method, string $url, array $options = []): array
    {
        if (!empty($options['query']) && is_array($options['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($options['query'], '', '&', PHP_QUERY_RFC3986);
        }

        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $args = [
            'method' => strtoupper($method),
            'timeout' => min(15, max(1, (int) ($options['timeout'] ?? 10))),
            'redirection' => 2,
            'headers' => $headers,
        ];

        if (array_key_exists('json', $options)) {
            $encoded = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = $encoded;
        } elseif (array_key_exists('body', $options)) {
            $args['body'] = $options['body'];
        }

        $raw = ($this->requester)($url, $args);
        if (function_exists('is_wp_error') && is_wp_error($raw)) {
            throw ProviderException::fromResponse('Provider transport failed', 0, ['error' => $raw->get_error_message()]);
        }
        if (!is_array($raw)) {
            throw ProviderException::fromResponse('Provider returned an invalid transport response', 0);
        }

        $status = (int) ($raw['response']['code'] ?? 0);
        $rawBody = (string) ($raw['body'] ?? '');
        try {
            $body = $rawBody === '' ? [] : json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ProviderException::fromResponse('Provider returned a non-JSON response', $status, [
                'body_preview' => substr(strip_tags($rawBody), 0, 200),
            ]);
        }
        if (!is_array($body)) {
            throw ProviderException::fromResponse('Provider returned an invalid JSON response', $status);
        }

        $responseHeaders = $raw['headers'] ?? [];
        if (is_object($responseHeaders) && method_exists($responseHeaders, 'getAll')) {
            $responseHeaders = $responseHeaders->getAll();
        }

        return [
            'status' => $status,
            'body' => $body,
            'headers' => is_array($responseHeaders) ? $responseHeaders : [],
        ];
    }
}

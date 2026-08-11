<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

use Theobroma\Commerce\Contracts\Transport;

final class OzonClientFactory
{
    public function __construct(
        private readonly Transport $transport,
        private readonly TokenStore $tokens
    ) {
    }

    /** @param array<string,mixed> $settings */
    public function clientFromSettings(array $settings): OzonClient
    {
        return new OzonClient($this->transport, $this->authenticatorFromSettings($settings));
    }

    /** @param array<string,mixed> $settings */
    public function authenticatorFromSettings(array $settings): AccessTokenProvider
    {
        $clientId = defined('THEOBROMA_OZON_CLIENT_ID')
            ? (string) constant('THEOBROMA_OZON_CLIENT_ID')
            : (string) ($settings['ozon_client_id'] ?? '');
        $clientSecret = defined('THEOBROMA_OZON_CLIENT_SECRET')
            ? (string) constant('THEOBROMA_OZON_CLIENT_SECRET')
            : (string) ($settings['ozon_client_secret'] ?? '');

        return new OzonAuthenticator($this->transport, $this->tokens, $clientId, $clientSecret);
    }
}

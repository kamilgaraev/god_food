<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

final class OzonAuthorizationUrl
{
    private const SCOPES = [
        'seller-api.ozon-logistics',
        'seller-api.posting-fbo',
        'seller-api.posting-fbs',
        'seller-api.returns',
        'seller-api.report',
        'seller-api.product',
    ];

    public function build(string $clientId, string $redirectUri, string $state): string
    {
        return 'https://seller.ozon.ru/app/appstore/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'access_type' => 'offline',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'prompt' => 'select_company',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}


<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonAuthorizationUrl;

final class OzonAuthorizationUrlTest extends TestCase
{
    public function testBuildsOfflineSellerAuthorizationUrlWithRequiredScopes(): void
    {
        $url = (new OzonAuthorizationUrl())->build(
            'client-id',
            'https://shop.test/wp-json/theobroma-commerce/v1/ozon/oauth/callback',
            'csrf-state'
        );
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('seller.ozon.ru', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/app/appstore/oauth/authorize', parse_url($url, PHP_URL_PATH));
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('offline', $query['access_type'] ?? null);
        $this->assertSame('client-id', $query['client_id'] ?? null);
        $this->assertSame('https://shop.test/wp-json/theobroma-commerce/v1/ozon/oauth/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('csrf-state', $query['state'] ?? null);
        $this->assertSame('select_company', $query['prompt'] ?? null);
        $this->assertSame(
            'seller-api.ozon-logistics seller-api.posting-fbo seller-api.posting-fbs seller-api.returns seller-api.report seller-api.product',
            $query['scope'] ?? null
        );
    }
}


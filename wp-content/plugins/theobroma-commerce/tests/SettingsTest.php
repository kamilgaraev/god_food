<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\Settings;

final class SettingsTest extends TestCase
{
    public function testSanitizesFlagsCodesAndPreservesBlankSecrets(): void
    {
        $settings = new Settings();
        $actual = $settings->sanitize([
            'cdek_enabled' => 'yes',
            'cdek_client_id' => ' client-1 ',
            'cdek_client_secret' => '',
            'cdek_sender_city_code' => '-44',
            'ozon_approved' => '1',
            'ozon_access_token' => ' token-new ',
            'ozon_products_mapped' => 'no',
            'ozon_live_test_completed' => 'yes',
        ], [
            'cdek_client_secret' => 'secret-old',
            'ozon_access_token' => 'token-old',
        ]);

        $this->assertSame('yes', $actual['cdek_enabled']);
        $this->assertSame('client-1', $actual['cdek_client_id']);
        $this->assertSame('secret-old', $actual['cdek_client_secret']);
        $this->assertSame(0, $actual['cdek_sender_city_code']);
        $this->assertSame('yes', $actual['ozon_approved']);
        $this->assertSame('token-new', $actual['ozon_access_token']);
        $this->assertSame('no', $actual['ozon_products_mapped']);
        $this->assertSame('yes', $actual['ozon_live_test_completed']);
    }

    public function testReturnsSafeDefaults(): void
    {
        $defaults = (new Settings())->defaults();
        $this->assertSame('no', $defaults['cdek_enabled']);
        $this->assertSame('no', $defaults['ozon_enabled']);
        $this->assertSame('', $defaults['cdek_client_secret']);
        $this->assertSame('', $defaults['ozon_access_token']);
    }
}

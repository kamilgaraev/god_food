<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\Settings;

final class SettingsTest extends TestCase
{
    public function testSanitizesCredentialsAndPreservesBlankSecrets(): void
    {
        $settings = new Settings();
        $actual = $settings->sanitize([
            'cdek_enabled' => 'yes',
            'cdek_client_id' => ' client-1 ',
            'cdek_client_secret' => '',
            'cdek_sender_city_code' => '-44',
            'ozon_client_id' => ' ozon-client ',
            'ozon_client_secret' => '',
        ], [
            'cdek_client_secret' => 'secret-old',
            'ozon_client_secret' => 'ozon-secret-old',
            'ozon_access_token' => 'legacy-token',
            'ozon_approved' => 'yes',
        ]);

        $this->assertSame('yes', $actual['cdek_enabled']);
        $this->assertSame('client-1', $actual['cdek_client_id']);
        $this->assertSame('secret-old', $actual['cdek_client_secret']);
        $this->assertSame(0, $actual['cdek_sender_city_code']);
        $this->assertSame('ozon-client', $actual['ozon_client_id']);
        $this->assertSame('ozon-secret-old', $actual['ozon_client_secret']);
        $this->assertSame(false, array_key_exists('ozon_access_token', $actual));
        $this->assertSame(false, array_key_exists('ozon_approved', $actual));
    }

    public function testReturnsSafeDefaults(): void
    {
        $defaults = (new Settings())->defaults();
        $this->assertSame('no', $defaults['cdek_enabled']);
        $this->assertSame('', $defaults['ozon_client_id']);
        $this->assertSame('', $defaults['cdek_client_secret']);
        $this->assertSame('', $defaults['ozon_client_secret']);
        $this->assertSame(false, array_key_exists('ozon_access_token', $defaults));
    }

    public function testDetectsOnlyActualOzonCredentialChanges(): void
    {
        $settings = new Settings();
        $existing = ['ozon_client_id' => 'client-1', 'ozon_client_secret' => 'secret-1'];

        $this->assertSame(false, $settings->ozonCredentialsChanged($existing, $existing));
        $this->assertSame(true, $settings->ozonCredentialsChanged($existing, [
            'ozon_client_id' => 'client-2',
            'ozon_client_secret' => 'secret-1',
        ]));
        $this->assertSame(true, $settings->ozonCredentialsChanged($existing, [
            'ozon_client_id' => 'client-1',
            'ozon_client_secret' => 'secret-2',
        ]));
    }
}

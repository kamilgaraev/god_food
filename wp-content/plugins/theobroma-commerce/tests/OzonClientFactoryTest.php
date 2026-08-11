<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonClientFactoryTest extends TestCase
{
    public function testBuildsAuthenticatorFromSavedCredentials(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [
                'access_token' => 'factory-token',
                'refresh_token' => 'factory-refresh-token',
                'expires_in' => 3600,
            ]],
        ]);
        $factory = new OzonClientFactory($transport, new MemoryOzonTokenStore());

        $authenticator = $factory->authenticatorFromSettings([
            'ozon_client_id' => 'client-from-settings',
            'ozon_client_secret' => 'secret-from-settings',
        ]);
        $authenticator->authorize('factory-code', 'https://shop.test/ozon/callback');

        $this->assertSame('https://xapi.ozon.ru/oauth/token', $transport->requests[0]['url']);
        $this->assertSame('client-from-settings', $transport->requests[0]['options']['json']['client_id']);
        $this->assertSame('secret-from-settings', $transport->requests[0]['options']['json']['client_secret']);
    }
}

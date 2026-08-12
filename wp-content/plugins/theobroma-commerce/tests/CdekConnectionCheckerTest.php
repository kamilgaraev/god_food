<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\CdekConnectionChecker;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Tests\Fakes\MemoryTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class CdekConnectionCheckerTest extends TestCase
{
    public function testRequestsFreshProductionTokenWithoutReturningIt(): void
    {
        $tokens = new MemoryTokenStore();
        $tokens->put('cached-token', time() + 3000);
        $transport = new RecordingTransport([[
            'status' => 200,
            'body' => [
                'access_token' => 'fresh-token-never-render-me',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ],
        ]]);
        $client = new CdekClient($transport, $tokens, 'account-42', 'secure-password-42');

        $result = (new CdekConnectionChecker())->check($client);

        $this->assertSame('success', $result['status']);
        $this->assertSame(false, str_contains($result['message'], 'fresh-token-never-render-me'));
        $this->assertSame(1, count($transport->requests));
        $this->assertSame('https://api.cdek.ru/v2/oauth/token', $transport->requests[0]['url']);
        $this->assertSame([
            'grant_type' => 'client_credentials',
            'client_id' => 'account-42',
            'client_secret' => 'secure-password-42',
        ], $transport->requests[0]['options']['body']);
        $this->assertSame('fresh-token-never-render-me', $tokens->value['token'] ?? null);
    }

    public function testReturnsSafeErrorForRejectedCredentials(): void
    {
        $transport = new RecordingTransport([[
            'status' => 401,
            'body' => [
                'error' => 'invalid_client',
                'error_description' => 'secure-password-42 rejected',
            ],
        ]]);
        $client = new CdekClient(
            $transport,
            new MemoryTokenStore(),
            'account-42',
            'secure-password-42'
        );

        $result = (new CdekConnectionChecker())->check($client);

        $this->assertSame('error', $result['status']);
        $this->assertSame(true, str_contains($result['message'], 'HTTP 401'));
        $this->assertSame(false, str_contains($result['message'], 'secure-password-42'));
    }

    public function testRejectsMissingCredentialsWithoutNetworkRequest(): void
    {
        $transport = new RecordingTransport([]);
        $client = new CdekClient($transport, new MemoryTokenStore(), '', '');

        $result = (new CdekConnectionChecker())->check($client);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Сначала сохраните Account и Secure password СДЭК.', $result['message']);
        $this->assertSame(0, count($transport->requests));
    }
}

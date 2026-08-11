<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonAuthenticator;
use Theobroma\Commerce\Support\ProviderException;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonAuthenticatorTest extends TestCase
{
    public function testObtainsAndCachesTokenFromClientCredentials(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['access_token' => 'ozon-token', 'expires_in' => 3600]],
        ]);
        $tokens = new MemoryOzonTokenStore();
        $before = time();

        $token = (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))->token();

        $this->assertSame('ozon-token', $token);
        $this->assertSame('POST', $transport->requests[0]['method']);
        $this->assertSame('https://api-seller.ozon.ru/oauth/token', $transport->requests[0]['url']);
        $this->assertSame([
            'grant_type' => 'client_credentials',
            'client_id' => 'client-42',
            'client_secret' => 'secret-42',
        ], $transport->requests[0]['options']['body']);
        $this->assertSame('application/json', $transport->requests[0]['options']['headers']['Accept']);
        $this->assertTrue(($tokens->value['expires_at'] ?? 0) >= $before + 3600);
    }

    public function testUsesCachedTokenUntilItApproachesExpiry(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = ['token' => 'cached-token', 'expires_at' => time() + 61];
        $transport = new RecordingTransport([]);
        $authenticator = new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42');

        $this->assertSame('cached-token', $authenticator->token());
        $this->assertSame(0, count($transport->requests));
    }

    public function testRefreshesTokenWithSixtySecondsOrLessRemaining(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = ['token' => 'expiring-token', 'expires_at' => time() + 60];
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['access_token' => 'fresh-token', 'expires_in' => 120]],
        ]);

        $token = (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))->token();

        $this->assertSame('fresh-token', $token);
        $this->assertSame(1, count($transport->requests));
    }

    public function testRejectsMissingCredentialsBeforeNetworkRequest(): void
    {
        $transport = new RecordingTransport([]);
        $exception = $this->assertThrows(
            static fn (): string => (new OzonAuthenticator($transport, new MemoryOzonTokenStore(), '', ''))->token(),
            ProviderException::class
        );

        $this->assertSame(0, count($transport->requests));
        $this->assertSame(false, str_contains($exception->getMessage(), 'secret'));
    }

    public function testRejectsMalformedTokenResponseWithoutCachingIt(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['expires_in' => 3600]],
        ]);
        $tokens = new MemoryOzonTokenStore();

        $this->assertThrows(
            static fn (): string => (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))->token(),
            ProviderException::class
        );

        $this->assertSame(null, $tokens->value);
    }

    public function testRejectsTokenWithoutPositiveExpiry(): void
    {
        foreach ([null, 0, -10, 'invalid'] as $expiresIn) {
            $body = ['access_token' => 'token-with-bad-expiry'];
            if ($expiresIn !== null) {
                $body['expires_in'] = $expiresIn;
            }
            $tokens = new MemoryOzonTokenStore();
            $authenticator = new OzonAuthenticator(
                new RecordingTransport([['status' => 200, 'body' => $body]]),
                $tokens,
                'client-42',
                'secret-42'
            );

            $this->assertThrows(static fn (): string => $authenticator->token(), ProviderException::class);
            $this->assertSame(null, $tokens->value);
        }
    }
}

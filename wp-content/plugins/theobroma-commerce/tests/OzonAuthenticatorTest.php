<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonAuthenticator;
use Theobroma\Commerce\Support\ProviderException;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonAuthenticatorTest extends TestCase
{
    public function testExchangesAuthorizationCodeAndCachesSellerTokens(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [
                'access_token' => 'seller-access-token',
                'refresh_token' => 'seller-refresh-token',
                'expires_in' => 3600,
            ]],
        ]);
        $tokens = new MemoryOzonTokenStore();
        $before = time();
        $authenticator = new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42');

        $authenticator->authorize('authorization-code', 'https://shop.test/ozon/callback');

        $this->assertSame('POST', $transport->requests[0]['method']);
        $this->assertSame('https://xapi.ozon.ru/oauth/token', $transport->requests[0]['url']);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'client_id' => 'client-42',
            'client_secret' => 'secret-42',
            'redirect_uri' => 'https://shop.test/ozon/callback',
            'code' => 'authorization-code',
        ], $transport->requests[0]['options']['json']);
        $this->assertSame('application/json', $transport->requests[0]['options']['headers']['Accept']);
        $this->assertSame('seller-access-token', $tokens->value['token'] ?? null);
        $this->assertSame('seller-refresh-token', $tokens->value['refresh_token'] ?? null);
        $this->assertTrue(($tokens->value['expires_at'] ?? 0) >= $before + 3600);
    }

    public function testUsesCachedSellerTokenUntilItApproachesExpiry(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = [
            'token' => 'cached-token',
            'expires_at' => time() + 61,
            'refresh_token' => 'refresh-token',
        ];
        $transport = new RecordingTransport([]);
        $authenticator = new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42');

        $this->assertSame('cached-token', $authenticator->token());
        $this->assertSame(0, count($transport->requests));
    }

    public function testRefreshesExpiringSellerTokenAndRotatesRefreshToken(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = [
            'token' => 'expiring-token',
            'expires_at' => time() + 60,
            'refresh_token' => 'old-refresh-token',
        ];
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => [
                'access_token' => 'fresh-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 120,
            ]],
        ]);

        $token = (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))->token();

        $this->assertSame('fresh-token', $token);
        $this->assertSame('https://xapi.ozon.ru/oauth/token', $transport->requests[0]['url']);
        $this->assertSame([
            'grant_type' => 'refresh_token',
            'client_id' => 'client-42',
            'client_secret' => 'secret-42',
            'refresh_token' => 'old-refresh-token',
        ], $transport->requests[0]['options']['json']);
        $this->assertSame('new-refresh-token', $tokens->value['refresh_token'] ?? null);
    }

    public function testPreservesRefreshTokenWhenRefreshResponseDoesNotRotateIt(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = [
            'token' => 'expired-token',
            'expires_at' => time() - 1,
            'refresh_token' => 'stable-refresh-token',
        ];
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['access_token' => 'fresh-token', 'expires_in' => 120]],
        ]);

        (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))->token();

        $this->assertSame('stable-refresh-token', $tokens->value['refresh_token'] ?? null);
    }

    public function testInvalidatingAccessTokenPreservesSellerAuthorizationForRefresh(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = [
            'token' => 'rejected-access-token',
            'expires_at' => time() + 3600,
            'refresh_token' => 'seller-refresh-token',
        ];
        $transport = new RecordingTransport([[
            'status' => 200,
            'body' => ['access_token' => 'replacement-token', 'expires_in' => 3600],
        ]]);
        $authenticator = new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42');

        $authenticator->forget();
        $token = $authenticator->token();

        $this->assertSame('replacement-token', $token);
        $this->assertSame('seller-refresh-token', $tokens->value['refresh_token'] ?? null);
        $this->assertSame('seller-refresh-token', $transport->requests[0]['options']['json']['refresh_token'] ?? null);
    }

    public function testRejectsMissingSellerAuthorizationWithoutNetworkRequest(): void
    {
        $transport = new RecordingTransport([]);
        $exception = $this->assertThrows(
            static fn (): string => (new OzonAuthenticator(
                $transport,
                new MemoryOzonTokenStore(),
                'client-42',
                'secret-42'
            ))->token(),
            ProviderException::class
        );

        $this->assertSame(0, count($transport->requests));
        $this->assertSame(false, str_contains($exception->getMessage(), 'secret-42'));
    }

    public function testRejectsMissingCredentialsBeforeAuthorizationExchange(): void
    {
        $transport = new RecordingTransport([]);
        $exception = $this->assertThrows(
            static function () use ($transport): void {
                (new OzonAuthenticator($transport, new MemoryOzonTokenStore(), '', ''))
                    ->authorize('code', 'https://shop.test/ozon/callback');
            },
            ProviderException::class
        );

        $this->assertSame(0, count($transport->requests));
        $this->assertSame(false, str_contains($exception->getMessage(), 'secret'));
    }

    public function testRejectsMalformedAuthorizationResponseWithoutCachingIt(): void
    {
        $transport = new RecordingTransport([
            ['status' => 200, 'body' => ['access_token' => 'access-only', 'expires_in' => 3600]],
        ]);
        $tokens = new MemoryOzonTokenStore();

        $this->assertThrows(
            static function () use ($transport, $tokens): void {
                (new OzonAuthenticator($transport, $tokens, 'client-42', 'secret-42'))
                    ->authorize('code', 'https://shop.test/ozon/callback');
            },
            ProviderException::class
        );

        $this->assertSame(null, $tokens->value);
    }
}

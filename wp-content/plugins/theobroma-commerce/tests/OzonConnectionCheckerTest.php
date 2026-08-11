<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\OzonConnectionChecker;
use Theobroma\Commerce\Integrations\Ozon\OzonAuthenticator;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;
use Theobroma\Commerce\Tests\Fakes\StaticAccessTokenProvider;

final class OzonConnectionCheckerTest extends TestCase
{
    public function testReportsSuccessfulAuthenticationWithoutReturningToken(): void
    {
        $authenticator = new StaticAccessTokenProvider(['never-render-me']);

        $result = (new OzonConnectionChecker())->check($authenticator);

        $this->assertSame('success', $result['status']);
        $this->assertSame(false, str_contains($result['message'], 'never-render-me'));
        $this->assertSame(0, $authenticator->forgetCalls);
    }

    public function testReturnsSafeErrorForRejectedAuthentication(): void
    {
        $tokens = new MemoryOzonTokenStore();
        $tokens->value = [
            'token' => 'expired-access-token',
            'expires_at' => time() - 1,
            'refresh_token' => 'seller-refresh-token',
        ];
        $authenticator = new OzonAuthenticator(
            new RecordingTransport([['status' => 401, 'body' => ['message' => 'secret-42 rejected']]]),
            $tokens,
            'client-42',
            'secret-42'
        );

        $result = (new OzonConnectionChecker())->check($authenticator);

        $this->assertSame('error', $result['status']);
        $this->assertSame(true, str_contains($result['message'], 'HTTP 401'));
        $this->assertSame(false, str_contains($result['message'], 'secret-42'));
    }
}

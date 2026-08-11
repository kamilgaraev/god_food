<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\OzonConnectionChecker;
use Theobroma\Commerce\Integrations\Ozon\OzonAuthenticator;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonConnectionCheckerTest extends TestCase
{
    public function testReportsSuccessfulAuthenticationWithoutReturningToken(): void
    {
        $authenticator = new OzonAuthenticator(
            new RecordingTransport([['status' => 200, 'body' => ['access_token' => 'never-render-me', 'expires_in' => 3600]]]),
            new MemoryOzonTokenStore(),
            'client-42',
            'secret-42'
        );

        $result = (new OzonConnectionChecker())->check($authenticator);

        $this->assertSame('success', $result['status']);
        $this->assertSame(false, str_contains($result['message'], 'never-render-me'));
    }

    public function testReturnsSafeErrorForRejectedAuthentication(): void
    {
        $authenticator = new OzonAuthenticator(
            new RecordingTransport([['status' => 401, 'body' => ['message' => 'secret-42 rejected']]]),
            new MemoryOzonTokenStore(),
            'client-42',
            'secret-42'
        );

        $result = (new OzonConnectionChecker())->check($authenticator);

        $this->assertSame('error', $result['status']);
        $this->assertSame(true, str_contains($result['message'], 'HTTP 401'));
        $this->assertSame(false, str_contains($result['message'], 'secret-42'));
    }
}

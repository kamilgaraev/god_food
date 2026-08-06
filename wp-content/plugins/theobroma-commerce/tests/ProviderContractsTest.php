<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Infrastructure\SecretRedactor;
use Theobroma\Commerce\Support\ProviderException;

final class ProviderContractsTest extends TestCase
{
    public function testRedactsNestedSecretsWithoutMutatingSafeDiagnostics(): void
    {
        $redactor = new SecretRedactor();
        $input = [
            'client_id' => 'safe-id',
            'client_secret' => 'top-secret',
            'headers' => ['Authorization' => 'Bearer abc', 'Accept' => 'application/json'],
            'payload' => ['api_key' => 'key-123', 'order' => 42],
        ];

        $this->assertSame([
            'client_id' => 'safe-id',
            'client_secret' => '[redacted]',
            'headers' => ['Authorization' => '[redacted]', 'Accept' => 'application/json'],
            'payload' => ['api_key' => '[redacted]', 'order' => 42],
        ], $redactor->redact($input));
    }

    public function testProviderExceptionExposesSafeContextOnly(): void
    {
        $exception = ProviderException::fromResponse('CDEK request failed', 401, [
            'client_secret' => 'top-secret',
            'request_id' => 'request-1',
        ]);

        $this->assertSame(401, $exception->statusCode());
        $this->assertSame(['client_secret' => '[redacted]', 'request_id' => 'request-1'], $exception->context());
    }
}

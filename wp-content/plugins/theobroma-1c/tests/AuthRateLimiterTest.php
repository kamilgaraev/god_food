<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

use Theobroma\OneC\Http\AuthRateLimiter;

final class AuthRateLimiterTest
{
    public function testBlocksBucketAfterConfiguredFailuresAndCanReset(): void
    {
        $state = [];
        $limiter = new AuthRateLimiter(
            static function (string $key) use (&$state): int { return (int) ($state[$key] ?? 0); },
            static function (string $key, int $value) use (&$state): void { $state[$key] = $value; },
            static function (string $key) use (&$state): void { unset($state[$key]); },
            3
        );

        $this->same(true, $limiter->allowed('bucket'));
        $limiter->failure('bucket');
        $limiter->failure('bucket');
        $limiter->failure('bucket');
        $this->same(false, $limiter->allowed('bucket'));
        $limiter->success('bucket');
        $this->same(true, $limiter->allowed('bucket'));
    }

    private function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}

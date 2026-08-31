<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Admin\YandexMapsConnectionChecker;

final class YandexMapsConnectionCheckerTest extends TestCase
{
    public function testReportsListFallbackWhenKeysAreMissing(): void
    {
        $checker = new YandexMapsConnectionChecker(
            static fn (string $key): array => ['status' => 500, 'body' => 'must not run'],
            static fn (string $key): array => ['status' => 500, 'body' => 'must not run']
        );

        $result = $checker->check('', '');

        $this->assertSame('not_configured', $result['javascript']['status']);
        $this->assertSame('not_configured', $result['geocoder']['status']);
        $this->assertSame(true, $result['list_fallback']);
    }

    public function testValidatesBothKeysWithoutReturningTheirValues(): void
    {
        $checker = new YandexMapsConnectionChecker(
            static fn (string $key): array => ['status' => 200, 'body' => 'ymaps.ready(function(){})'],
            static fn (string $key): array => ['status' => 200, 'body' => '{"response":{"GeoObjectCollection":{}}}']
        );

        $result = $checker->check('public-js-key', 'private-geocoder-key');

        $this->assertSame('valid', $result['javascript']['status']);
        $this->assertSame('valid', $result['geocoder']['status']);
        $this->assertSame(false, $result['list_fallback']);
        $encoded = json_encode($result);
        $this->assertSame(false, str_contains((string) $encoded, 'public-js-key'));
        $this->assertSame(false, str_contains((string) $encoded, 'private-geocoder-key'));
    }

    public function testTreatsForbiddenProbeAsInvalidAndKeepsFallback(): void
    {
        $checker = new YandexMapsConnectionChecker(
            static fn (string $key): array => ['status' => 403, 'body' => 'Invalid apikey'],
            static fn (string $key): array => ['status' => 403, 'body' => '{"message":"Invalid apikey"}']
        );

        $result = $checker->check('bad-js-key', 'bad-geocoder-key');

        $this->assertSame('invalid', $result['javascript']['status']);
        $this->assertSame('invalid', $result['geocoder']['status']);
        $this->assertSame(true, $result['list_fallback']);
    }
}

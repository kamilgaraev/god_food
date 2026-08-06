<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Support\ProviderException;

final class WpTransportTest extends TestCase
{
    public function testEncodesJsonAndBoundsTimeout(): void
    {
        $recorded = [];
        $transport = new WpTransport(static function (string $url, array $args) use (&$recorded): array {
            $recorded = compact('url', 'args');
            return ['response' => ['code' => 200], 'headers' => ['x-request-id' => 'r1'], 'body' => '{"ok":true}'];
        });

        $response = $transport->request('POST', 'https://example.test/resource', [
            'timeout' => 120,
            'headers' => ['Authorization' => 'Bearer x'],
            'json' => ['order' => 42],
        ]);

        $this->assertSame(15, $recorded['args']['timeout']);
        $this->assertSame('POST', $recorded['args']['method']);
        $this->assertSame('application/json', $recorded['args']['headers']['Content-Type']);
        $this->assertSame('{"order":42}', $recorded['args']['body']);
        $this->assertSame(['ok' => true], $response['body']);
    }

    public function testRejectsNonJsonProviderResponse(): void
    {
        $transport = new WpTransport(static fn (): array => [
            'response' => ['code' => 502],
            'headers' => [],
            'body' => '<html>bad gateway</html>',
        ]);

        $exception = $this->assertThrows(
            static fn () => $transport->request('GET', 'https://example.test/resource'),
            ProviderException::class
        );

        $this->assertSame(502, $exception->statusCode());
    }
}

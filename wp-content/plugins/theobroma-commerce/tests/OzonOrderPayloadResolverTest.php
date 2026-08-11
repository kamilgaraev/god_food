<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\OzonOrderPayloadResolver;

final class OzonOrderPayloadResolverTest extends TestCase
{
    public function testUsesExistingOrderPayloadBeforeShippingMetadata(): void
    {
        $resolver = new OzonOrderPayloadResolver();

        $payload = $resolver->resolve(
            ['buyer' => ['phone' => 'order-level']],
            [new OzonShippingItemPayload('{"buyer":{"phone":"shipping"}}')]
        );

        $this->assertSame(['buyer' => ['phone' => 'order-level']], $payload);
    }

    public function testDecodesPayloadCopiedIntoSelectedShippingItem(): void
    {
        $resolver = new OzonOrderPayloadResolver();

        $payload = $resolver->resolve('', [
            new OzonShippingItemPayload(''),
            new OzonShippingItemPayload('{"delivery_schema":"MIX","splits":[]}'),
        ]);

        $this->assertSame(['delivery_schema' => 'MIX', 'splits' => []], $payload);
    }

    public function testRejectsMissingOrMalformedShippingPayload(): void
    {
        $resolver = new OzonOrderPayloadResolver();

        $this->assertSame(null, $resolver->resolve(null, []));
        $this->assertSame(null, $resolver->resolve(null, [new OzonShippingItemPayload('{bad-json')]));
        $this->assertSame(null, $resolver->resolve(null, [new OzonShippingItemPayload('[]')]));
    }
}

final class OzonShippingItemPayload
{
    public function __construct(private readonly string $payload)
    {
    }

    public function get_meta(string $key, bool $single): string
    {
        return $key === 'theobroma_ozon_create_payload' && $single ? $this->payload : '';
    }
}

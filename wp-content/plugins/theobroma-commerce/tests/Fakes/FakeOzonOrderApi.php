<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Ozon\OzonOrderApi;

final class FakeOzonOrderApi implements OzonOrderApi
{
    /** @var list<array<mixed>> */
    public array $created = [];

    /** @param array<mixed> $response */
    public function __construct(private readonly array $response)
    {
    }

    public function createOrder(array $payload): array
    {
        $this->created[] = $payload;
        return $this->response;
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Cdek\CdekOrderApi;

final class FakeCdekOrderApi implements CdekOrderApi
{
    public int $createCalls = 0;

    /** @param array<mixed> $getResponse */
    public function __construct(private readonly array $getResponse = [])
    {
    }

    public function createOrder(array $payload): array
    {
        $this->createCalls++;
        return ['uuid' => 'cdek-uuid-1'];
    }

    public function getOrder(string $uuid): array
    {
        return $this->getResponse !== [] ? $this->getResponse : ['uuid' => $uuid, 'cdek_number' => '123456'];
    }
}

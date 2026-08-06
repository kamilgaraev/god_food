<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

interface OzonOrderApi
{
    /** @param array<mixed> $payload @return array<mixed> */
    public function createOrder(array $payload): array;
}

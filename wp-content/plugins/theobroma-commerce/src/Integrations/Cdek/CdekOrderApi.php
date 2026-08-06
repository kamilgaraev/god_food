<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Cdek;

interface CdekOrderApi
{
    /** @param array<mixed> $payload @return array<mixed> */
    public function createOrder(array $payload): array;

    /** @return array<mixed> */
    public function getOrder(string $uuid): array;
}

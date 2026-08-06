<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Contracts;

interface Transport
{
    /**
     * @param array<mixed> $options
     * @return array{status:int,body:array<mixed>,headers?:array<mixed>}
     */
    public function request(string $method, string $url, array $options = []): array;
}

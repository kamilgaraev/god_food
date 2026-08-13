<?php
declare(strict_types=1);

namespace Theobroma\OneC\Orders;

final readonly class ServiceLineData
{
    public function __construct(public string $id, public string $name, public string $total) {}
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

interface ShipmentOrder
{
    public function id(): int;
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
    public function note(string $message): void;
    public function save(): void;
}

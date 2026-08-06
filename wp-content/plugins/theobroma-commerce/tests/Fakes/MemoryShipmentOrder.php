<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Orders\ShipmentOrder;

final class MemoryShipmentOrder implements ShipmentOrder
{
    /** @var array<string,mixed> */
    public array $meta = [];
    /** @var list<string> */
    public array $notes = [];
    public int $saves = 0;

    public function __construct(private readonly int $id = 42)
    {
    }

    public function id(): int { return $this->id; }
    public function get(string $key): mixed { return $this->meta[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->meta[$key] = $value; }
    public function note(string $message): void { $this->notes[] = $message; }
    public function save(): void { $this->saves++; }
}

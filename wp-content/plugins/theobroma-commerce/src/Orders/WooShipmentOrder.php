<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class WooShipmentOrder implements ShipmentOrder
{
    public function __construct(private readonly \WC_Order $order)
    {
    }

    public function id(): int { return $this->order->get_id(); }
    public function get(string $key): mixed { return $this->order->get_meta($key, true); }
    public function set(string $key, mixed $value): void { $this->order->update_meta_data($key, $value); }
    public function note(string $message): void { $this->order->add_order_note($message); }
    public function save(): void { $this->order->save(); }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class ShipmentDispatchPolicy
{
    public function shouldDispatch(string $event, string $paymentMethod, bool $paid): bool
    {
        if ($event === 'checkout') {
            return $paymentMethod === 'cod';
        }

        return $event === 'processing' && $paid;
    }
}

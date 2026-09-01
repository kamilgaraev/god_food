<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class ShippingRateCache
{
    public function __construct(private readonly mixed $session = null)
    {
    }

    public function invalidate(): void
    {
        $session = $this->session;
        if ($session === null && function_exists('WC')) {
            $session = WC()->session ?? null;
        }
        if (!is_object($session) || !method_exists($session, 'get_session_data') || !method_exists($session, 'set')) {
            return;
        }

        foreach (array_keys((array) $session->get_session_data()) as $key) {
            if (is_string($key) && str_starts_with($key, 'shipping_for_package_')) {
                $session->set($key, null);
            }
        }
    }
}

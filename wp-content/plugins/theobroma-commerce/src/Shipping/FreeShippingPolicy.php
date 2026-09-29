<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

final class FreeShippingPolicy
{
    public const MINIMUM_RUBLES = 3000;

    public static function cartAmount(): float
    {
        $woocommerce = function_exists('WC') ? WC() : null;
        $cart = is_object($woocommerce) ? ($woocommerce->cart ?? null) : null;
        if (!is_object($cart) || !method_exists($cart, 'get_cart_contents_total') || !method_exists($cart, 'get_cart_contents_tax')) {
            return 0.0;
        }

        // WooCommerce line totals already include coupon discounts; add merchandise tax, not shipping.
        return max(0.0, (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax());
    }

    public static function isEligible(float $merchandiseAmount): bool
    {
        return (int) round(max(0.0, $merchandiseAmount) * 100) >= self::MINIMUM_RUBLES * 100;
    }

    public static function customerCost(float $quotedCost, float $merchandiseAmount): float
    {
        return self::isEligible($merchandiseAmount) ? 0.0 : max(0.0, $quotedCost);
    }

    public static function customerLabel(string $label, float $merchandiseAmount): string
    {
        return self::isEligible($merchandiseAmount) ? rtrim($label) . ' — бесплатно' : $label;
    }
}

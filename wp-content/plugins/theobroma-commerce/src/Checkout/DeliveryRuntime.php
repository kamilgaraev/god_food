<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryRuntime
{
    /** @param array<string,mixed> $package */
    public static function fingerprint(array $package): string
    {
        $contents = is_array($package['contents'] ?? null) ? $package['contents'] : [];
        $items = [];
        foreach ($contents as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'variation_id' => (int) ($item['variation_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }
        $destination = is_array($package['destination'] ?? null) ? $package['destination'] : [];
        $destination['address'] = trim((string) ($destination['address'] ?? '') . ' ' . (string) ($destination['address_2'] ?? ''));
        return DeliveryFingerprint::fromData($items, $destination);
    }

    /** @return array<string,mixed> */
    public static function currentPackage(): array
    {
        $woocommerce = function_exists('WC') ? WC() : null;
        $cart = is_object($woocommerce) ? ($woocommerce->cart ?? null) : null;
        if ((!is_object($cart) || !method_exists($cart, 'get_cart')) && function_exists('wc_load_cart')) {
            wc_load_cart();
            $cart = is_object($woocommerce) ? ($woocommerce->cart ?? null) : null;
        }
        $customer = is_object($woocommerce) ? ($woocommerce->customer ?? null) : null;
        $contents = is_object($cart) && method_exists($cart, 'get_cart') ? $cart->get_cart() : [];
        $destination = [];
        if (is_object($customer)) {
            $destination = [
                'country' => method_exists($customer, 'get_shipping_country') ? $customer->get_shipping_country() : 'RU',
                'state' => method_exists($customer, 'get_shipping_state') ? $customer->get_shipping_state() : '',
                'city' => method_exists($customer, 'get_shipping_city') ? $customer->get_shipping_city() : '',
                'postcode' => method_exists($customer, 'get_shipping_postcode') ? $customer->get_shipping_postcode() : '',
                'address' => method_exists($customer, 'get_shipping_address_1') ? $customer->get_shipping_address_1() : '',
                'address_2' => method_exists($customer, 'get_shipping_address_2') ? $customer->get_shipping_address_2() : '',
            ];
        }
        return ['contents' => is_array($contents) ? $contents : [], 'destination' => $destination];
    }
}

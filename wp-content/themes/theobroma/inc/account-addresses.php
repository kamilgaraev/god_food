<?php

declare(strict_types=1);

/**
 * Keep one customer-facing delivery address while retaining WooCommerce's
 * checkout-compatible customer record internally.
 */
function theobroma_account_delivery_addresses(array $addresses, int $customer_id = 0): array
{
    return array('billing' => __('Адрес доставки', 'theobroma'));
}
add_filter('woocommerce_my_account_get_addresses', 'theobroma_account_delivery_addresses', 20, 2);

function theobroma_account_delivery_address_title(string $title, string $address_type): string
{
    return $address_type === 'billing' ? __('Адрес доставки', 'theobroma') : $title;
}
add_filter('woocommerce_account_edit_address_title', 'theobroma_account_delivery_address_title', 20, 2);

function theobroma_redirect_legacy_shipping_address(): void
{
    if (!is_wc_endpoint_url('edit-address') || get_query_var('edit-address') !== 'shipping') {
        return;
    }

    wp_safe_redirect(
        wc_get_endpoint_url('edit-address', 'billing', wc_get_page_permalink('myaccount')),
        302
    );
    exit;
}
add_action('template_redirect', 'theobroma_redirect_legacy_shipping_address', 20);

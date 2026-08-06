<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

Theobroma\Commerce\Installer::activate();

global $wpdb;
$accountTable = $wpdb->prefix . 'theobroma_loyalty_accounts';
$ledgerTable = $wpdb->prefix . 'theobroma_loyalty_ledger';
$suffix = wp_generate_password(8, false, false);
$username = 'theobroma_checkout_smoke_' . $suffix;
$userId = wp_insert_user([
    'user_login' => $username,
    'user_pass' => wp_generate_password(24, true, true),
    'user_email' => $username . '@example.invalid',
    'role' => 'customer',
]);
if (is_wp_error($userId)) {
    throw new RuntimeException($userId->get_error_message());
}

$product = null;
$order = null;
try {
    wp_set_current_user((int) $userId);
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    WC()->customer = new WC_Customer((int) $userId);
    WC()->cart = new WC_Cart();

    $product = new WC_Product_Simple();
    $product->set_name('Loyalty checkout smoke product');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_regular_price('1000');
    $product->set_price('1000');
    $product->set_virtual(true);
    $product->save();
    if (!WC()->cart->add_to_cart($product->get_id(), 1)) {
        throw new RuntimeException('Could not add loyalty smoke product to cart.');
    }
    WC()->cart->calculate_totals();

    $order = wc_create_order(['customer_id' => (int) $userId]);
    if (is_wp_error($order) || !$order instanceof WC_Order) {
        throw new RuntimeException('Could not create loyalty checkout smoke order.');
    }
    $order->add_product($product, 1, ['subtotal' => 1000, 'total' => 1000]);
    $order->calculate_totals(false);
    $order->save();

    $store = new Theobroma\Commerce\Loyalty\WpdbLoyaltyStore($wpdb);
    $store->mutate((int) $userId, 'smoke:checkout-seed:' . $order->get_id(), 'accrue', 50000, 0, $order->get_id());
    WC()->session->set('theobroma_bonus_requested_kopecks', 20000);

    $checkout = new Theobroma\Commerce\Loyalty\LoyaltyCheckout($store);
    $checkout->syncCoupon();
    WC()->cart->calculate_totals();
    if (!WC()->cart->has_discount('theobroma-bonus')) {
        throw new RuntimeException('Virtual loyalty coupon was not applied.');
    }
    if ((int) round(WC()->cart->get_coupon_discount_amount('theobroma-bonus', false) * 100) !== 20000) {
        throw new RuntimeException('Virtual loyalty coupon has an unexpected amount.');
    }

    $checkout->reserveForOrder($order);
    if ((int) $order->get_meta('_theobroma_bonus_reserved_kopecks', true) !== 20000) {
        throw new RuntimeException('Accepted bonus amount was not persisted on the order.');
    }
    if ($store->balance((int) $userId) !== ['available_kopecks' => 30000, 'reserved_kopecks' => 20000]) {
        throw new RuntimeException('Checkout did not reserve the accepted bonus amount atomically.');
    }

    $checkout->clearAfterOrder($order->get_id());
    if ((int) WC()->session->get('theobroma_bonus_requested_kopecks', 0) !== 0) {
        throw new RuntimeException('Checkout bonus session was not cleared.');
    }

    echo "Loyalty checkout smoke passed\n";
} finally {
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
    if ($product instanceof WC_Product) {
        $product->delete(true);
    }
    $wpdb->delete($ledgerTable, ['user_id' => (int) $userId], ['%d']);
    $wpdb->delete($accountTable, ['user_id' => (int) $userId], ['%d']);
    if (WC()->session) {
        WC()->session->destroy_session();
    }
    wp_delete_user((int) $userId);
}

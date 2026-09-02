<?php

declare(strict_types=1);

// The storefront loads checkout inside the cart modal via admin-ajax.php.
define('DOING_AJAX', true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once '/var/www/html/wp-load.php';
add_filter('pre_wp_mail', '__return_true');

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
$seedOrder = null;
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
    $checkout = new Theobroma\Commerce\Loyalty\LoyaltyCheckout($store);

    ob_start();
    woocommerce_checkout_payment();
    $paymentHtml = (string) ob_get_clean();
    if (substr_count($paymentHtml, 'id="theobroma_bonus_amount"') !== 1) {
        throw new RuntimeException('AJAX payment fragment must contain exactly one usable bonus input.');
    }
    if (str_contains($paymentHtml, '&lt;span class=')) {
        throw new RuntimeException('Bonus balances must render as formatted prices, not escaped HTML.');
    }
    if (is_checkout()) {
        throw new RuntimeException('This fixture must exercise the non-checkout modal entry point.');
    }
    $checkout->assets();
    if (!wp_script_is('theobroma-loyalty-checkout', 'enqueued')) {
        throw new RuntimeException('Bonus apply script must be available outside the checkout page.');
    }
    // Execute the actual AJAX handler, intercepting only WordPress's request termination.
    $applyAmount = static function (string $amount) use ($checkout): array {
        $originalPost = $_POST;
        $originalRequest = $_REQUEST;
        $_POST = ['amount' => $amount, 'nonce' => wp_create_nonce('theobroma_set_bonus')];
        $_REQUEST = $_POST;
        $jsonExit = new RuntimeException('Test-only JSON response termination');
        $dieHandler = static fn () => static function () use ($jsonExit): void { throw $jsonExit; };
        add_filter('wp_die_ajax_handler', $dieHandler);
        ob_start();
        try {
            $checkout->ajaxSet();
        } catch (RuntimeException $exception) {
            if ($exception !== $jsonExit) {
                throw $exception;
            }
        } finally {
            $response = (string) ob_get_clean();
            remove_filter('wp_die_ajax_handler', $dieHandler);
            $_POST = $originalPost;
            $_REQUEST = $originalRequest;
        }
        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    };
    $response = $applyAmount('999');
    if (empty($response['success']) || $response['data']['accepted_kopecks'] !== 20000) {
        throw new RuntimeException('AJAX must cap redemption at 20 percent of merchandise.');
    }
    if ((int) round((float) WC()->cart->get_total('edit') * 100) !== 80000) {
        throw new RuntimeException('Applying bonuses must reduce the actual checkout total.');
    }
    $response = $applyAmount('0');
    if (empty($response['success']) || WC()->cart->has_discount('theobroma-bonus')
        || (int) round((float) WC()->cart->get_total('edit') * 100) !== 100000) {
        throw new RuntimeException('Removing bonuses must restore the checkout total.');
    }
    $applyAmount('200');
    if (!WC()->cart->has_discount('theobroma-bonus')) {
        throw new RuntimeException('Virtual loyalty coupon was not applied.');
    }
    if ((int) round(WC()->cart->get_coupon_discount_amount('theobroma-bonus', false) * 100) !== 20000) {
        throw new RuntimeException('Virtual loyalty coupon has an unexpected amount.');
    }

    $seedOrder = $order;
    $orderId = WC()->checkout()->create_order([
        'billing_first_name' => 'Loyalty smoke',
        'billing_email' => $username . '@example.invalid',
        'payment_method' => 'cod',
    ]);
    if (is_wp_error($orderId)) {
        throw new RuntimeException($orderId->get_error_message());
    }
    $order = wc_get_order($orderId);
    if (!$order instanceof WC_Order || (int) round((float) $order->get_total() * 100) !== 80000) {
        throw new RuntimeException('The persisted order must retain the bonus discount.');
    }
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
    if ($seedOrder instanceof WC_Order) {
        $seedOrder->delete(true);
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

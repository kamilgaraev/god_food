<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
add_filter('pre_wp_mail', '__return_true');

Theobroma\Commerce\Installer::activate();

global $wpdb;
$accountTable = $wpdb->prefix . 'theobroma_loyalty_accounts';
$ledgerTable = $wpdb->prefix . 'theobroma_loyalty_ledger';
$username = 'theobroma_loyalty_smoke_' . wp_generate_password(8, false, false);
$userId = wp_insert_user([
    'user_login' => $username,
    'user_pass' => wp_generate_password(24, true, true),
    'user_email' => $username . '@example.invalid',
    'role' => 'customer',
]);
if (is_wp_error($userId)) {
    throw new RuntimeException($userId->get_error_message());
}

$order = null;
try {
    $order = wc_create_order(['customer_id' => $userId]);
    if (is_wp_error($order) || !$order instanceof WC_Order) {
        throw new RuntimeException('Could not create loyalty smoke order.');
    }

    $item = new WC_Order_Item_Product();
    $item->set_name('Loyalty smoke product');
    $item->set_quantity(1);
    $item->set_subtotal(100.00);
    $item->set_total(100.00);
    $order->add_item($item);
    $order->calculate_totals(false);
    $order->save();

    $store = new Theobroma\Commerce\Loyalty\WpdbLoyaltyStore($wpdb);
    $service = new Theobroma\Commerce\Loyalty\LoyaltyService($store);
    $store->mutate((int) $userId, 'smoke:seed:' . $order->get_id(), 'accrue', 10000, 0, $order->get_id());
    $service->reserve((int) $userId, $order->get_id(), 2000);
    $order->update_meta_data('_theobroma_bonus_reserved_kopecks', 2000);
    $order->save();

    foreach (['pending', 'on-hold'] as $status) {
        $order->set_status($status);
        $order->save();
        (new Theobroma\Commerce\Loyalty\WooLoyaltyLifecycle($store))->onPaid($order->get_id());
        if ($store->balance((int) $userId) !== ['available_kopecks' => 8000, 'reserved_kopecks' => 2000]) {
            throw new RuntimeException('Unpaid order changed the reserved bonus balance.');
        }
    }

    $order->set_status('processing');
    $order->save();
    (new Theobroma\Commerce\Loyalty\WooLoyaltyLifecycle($store))->onPaid($order->get_id());

    $freshOrder = wc_get_order($order->get_id());
    if (!$freshOrder instanceof WC_Order) {
        throw new RuntimeException('Could not reload loyalty smoke order.');
    }
    if ((int) $freshOrder->get_meta('_theobroma_bonus_spent_kopecks', true) !== 2000) {
        throw new RuntimeException('Reserved bonuses were not converted to spent bonuses.');
    }
    if ((int) $freshOrder->get_meta('_theobroma_bonus_accrued_kopecks', true) !== 0) {
        throw new RuntimeException('Processing order must not accrue bonuses before completion.');
    }
    if ($store->balance((int) $userId) !== ['available_kopecks' => 8000, 'reserved_kopecks' => 0]) {
        throw new RuntimeException('Processing order must spend the reservation without accruing new bonuses.');
    }

    $freshOrder->update_status('completed');
    (new Theobroma\Commerce\Loyalty\WooLoyaltyLifecycle($store))->onPaid($order->get_id());
    $freshOrder = wc_get_order($order->get_id());
    $actualAccrual = (int) $freshOrder->get_meta('_theobroma_bonus_accrued_kopecks', true);
    if ($actualAccrual !== 500) {
        $savedItem = current($freshOrder->get_items('line_item'));
        throw new RuntimeException(sprintf(
            'Paid merchandise did not accrue five percent in bonuses: accrued=%d line_total=%s line_tax=%s order_total=%s.',
            $actualAccrual,
            $savedItem instanceof WC_Order_Item_Product ? $savedItem->get_total() : 'missing',
            $savedItem instanceof WC_Order_Item_Product ? $savedItem->get_total_tax() : 'missing',
            $freshOrder->get_total()
        ));
    }
    if ($store->balance((int) $userId) !== ['available_kopecks' => 8500, 'reserved_kopecks' => 0]) {
        throw new RuntimeException('Unexpected balance after paid-order lifecycle.');
    }
    if (count($store->history((int) $userId, 20, 0)) !== 4) {
        throw new RuntimeException('Paid-order lifecycle is not idempotent.');
    }

    echo "Loyalty order lifecycle smoke passed\n";
} finally {
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
    $wpdb->delete($ledgerTable, ['user_id' => (int) $userId], ['%d']);
    $wpdb->delete($accountTable, ['user_id' => (int) $userId], ['%d']);
    wp_delete_user((int) $userId);
}

<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
add_filter('pre_wp_mail', '__return_true');
Theobroma\Commerce\Installer::activate();

$username = 'loyalty_refund_smoke_' . wp_generate_password(8, false, false);
$userId = wp_insert_user([
    'user_login' => $username,
    'user_pass' => wp_generate_password(24, true, true),
    'user_email' => $username . '@example.invalid',
    'role' => 'customer',
]);
if (is_wp_error($userId)) { throw new RuntimeException($userId->get_error_message()); }
$store = new Theobroma\Commerce\Loyalty\WpdbLoyaltyStore($GLOBALS['wpdb']);
$service = new Theobroma\Commerce\Loyalty\LoyaltyService($store);
$orders = [];
$refunds = [];
$checkBalance = static function (int $expected) use ($store, $userId): void {
    if ($store->balance((int) $userId) !== ['available_kopecks' => $expected, 'reserved_kopecks' => 0]) {
        throw new RuntimeException('Unexpected balance: ' . json_encode($store->balance((int) $userId)) . ', expected ' . $expected);
    }
};
try {
    $store->mutate((int) $userId, 'refund-smoke:seed:' . $userId, 'accrue', 10000, 0, 0);
    foreach ([
        ['refund' => 20, 'cancel' => ''],
        ['refund' => 100, 'cancel' => ''],
        ['refund' => 20, 'cancel' => 'processing'],
        ['refund' => 20, 'cancel' => 'completed'],
    ] as $scenario) {
        $refundedBeforeCompletion = $scenario['refund'];
        $order = wc_create_order(['customer_id' => (int) $userId]);
        if (!$order instanceof WC_Order) { throw new RuntimeException('Could not create test order.'); }
        $orders[] = $order;
        $item = new WC_Order_Item_Product();
        $item->set_name('Refund regression product');
        $item->set_quantity(1);
        $item->set_subtotal(100);
        $item->set_total(100);
        $order->add_item($item);
        $order->calculate_totals(false);
        $service->reserve((int) $userId, $order->get_id(), 2000);
        $order->update_meta_data('_theobroma_bonus_reserved_kopecks', 2000);
        $order->save();
        $order->update_status('processing');
        $checkBalance(8000);

        $refundAmount = static function (int $amount) use ($order, $item, &$refunds): void {
            $refund = wc_create_refund([
                'order_id' => $order->get_id(),
                'amount' => $amount,
                'line_items' => [$item->get_id() => ['qty' => 0, 'refund_total' => $amount, 'refund_tax' => []]],
                'refund_payment' => false,
                'restock_items' => false,
            ]);
            if (is_wp_error($refund)) { throw new RuntimeException($refund->get_error_message()); }
            $refunds[] = $refund;
        };
        $refundAmount($refundedBeforeCompletion);
        $checkBalance($refundedBeforeCompletion === 20 ? 8400 : 10000);

        $order = wc_get_order($order->get_id());
        $lifecycle = new Theobroma\Commerce\Loyalty\WooLoyaltyLifecycle($store);
        if ($scenario['cancel'] === 'processing') {
            $order->update_status('cancelled');
            $lifecycle->onCancelled($order->get_id());
            $checkBalance(10000);
            continue;
        }
        $order->update_status('completed');
        $lifecycle->onPaid($order->get_id());
        $order = wc_get_order($order->get_id());
        $expectedAccrual = $refundedBeforeCompletion === 20 ? 400 : 0;
        if ((int) $order->get_meta('_theobroma_bonus_accrued_kopecks', true) !== $expectedAccrual) {
            throw new RuntimeException('Completion must accrue only on merchandise not already refunded.');
        }
        $checkBalance($refundedBeforeCompletion === 20 ? 8800 : 10000);

        if ($scenario['cancel'] === 'completed') {
            $refundAmount(30);
            $order = wc_get_order($order->get_id());
            $order->update_status('cancelled');
            $lifecycle->onCancelled($order->get_id());
            $checkBalance(10000);
            continue;
        }

        if ($refundedBeforeCompletion === 20) {
            $refundAmount(30);
            $checkBalance(9250);
            $lifecycle->onRefunded($order->get_id(), end($refunds)->get_id());
            $checkBalance(9250);
            $refundAmount(50);
            $checkBalance(10000);
        }
    }
    echo "Loyalty refunds before and after completion smoke passed\n";
} finally {
    foreach ($refunds as $refund) { $refund->delete(true); }
    foreach ($orders as $order) { $order->delete(true); }
    $GLOBALS['wpdb']->delete($GLOBALS['wpdb']->prefix . 'theobroma_loyalty_ledger', ['user_id' => (int) $userId], ['%d']);
    $GLOBALS['wpdb']->delete($GLOBALS['wpdb']->prefix . 'theobroma_loyalty_accounts', ['user_id' => (int) $userId], ['%d']);
    wp_delete_user((int) $userId);
}

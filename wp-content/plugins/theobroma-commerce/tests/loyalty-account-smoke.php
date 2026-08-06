<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

Theobroma\Commerce\Installer::activate();

global $wpdb;
$accountTable = $wpdb->prefix . 'theobroma_loyalty_accounts';
$ledgerTable = $wpdb->prefix . 'theobroma_loyalty_ledger';
$users = [];

try {
    foreach (['owner', 'other'] as $kind) {
        $login = 'theobroma_bonus_' . $kind . '_' . wp_generate_password(8, false, false);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass' => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.invalid',
            'role' => 'customer',
        ]);
        if (is_wp_error($userId)) {
            throw new RuntimeException($userId->get_error_message());
        }
        $users[$kind] = (int) $userId;
    }

    $store = new Theobroma\Commerce\Loyalty\WpdbLoyaltyStore($wpdb);
    $store->mutate($users['owner'], 'smoke:account:owner', 'accrue', 12500, 0, 900001, ['amount_kopecks' => 12500]);
    $store->mutate($users['other'], 'smoke:account:other', 'restore-spend', 9900, 0, 900002, ['amount_kopecks' => 9900]);
    wp_set_current_user($users['owner']);

    if (!has_action('woocommerce_account_bonuses_endpoint')) {
        throw new RuntimeException('Loyalty account endpoint callback is not registered.');
    }
    $menu = apply_filters('woocommerce_account_menu_items', [
        'dashboard' => 'Главная',
        'orders' => 'Заказы',
        'edit-address' => 'Адреса',
        'customer-logout' => 'Выйти',
    ]);
    if (array_keys($menu) !== ['dashboard', 'orders', 'bonuses', 'edit-address', 'edit-account', 'customer-logout']) {
        throw new RuntimeException('Loyalty menu entry has an unexpected position: ' . wp_json_encode(array_keys($menu)));
    }

    ob_start();
    do_action('woocommerce_account_bonuses_endpoint');
    $html = (string) ob_get_clean();
    if (!str_contains($html, 'Ваши бонусы') || !str_contains($html, 'Начислено за заказ')) {
        throw new RuntimeException('Loyalty account endpoint did not render balance and history.');
    }
    if (str_contains($html, 'Списанные бонусы возвращены')) {
        throw new RuntimeException('Loyalty account endpoint leaked another customer history.');
    }

    echo "Loyalty account endpoint smoke passed\n";
} finally {
    foreach ($users as $userId) {
        $wpdb->delete($ledgerTable, ['user_id' => $userId], ['%d']);
        $wpdb->delete($accountTable, ['user_id' => $userId], ['%d']);
        wp_delete_user($userId);
    }
}

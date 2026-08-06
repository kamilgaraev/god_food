<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

Theobroma\Commerce\Installer::activate();

global $wpdb;
$accountTable = $wpdb->prefix . 'theobroma_loyalty_accounts';
$ledgerTable = $wpdb->prefix . 'theobroma_loyalty_ledger';
$userId = 2147483000;

foreach ([$accountTable, $ledgerTable] as $table) {
    $engine = $wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $table
    ));
    if (strtoupper((string) $engine) !== 'INNODB') {
        throw new RuntimeException('Loyalty table must use InnoDB: ' . $table);
    }
}

try {
    $wpdb->delete($ledgerTable, ['user_id' => $userId], ['%d']);
    $wpdb->delete($accountTable, ['user_id' => $userId], ['%d']);

    $store = new Theobroma\Commerce\Loyalty\WpdbLoyaltyStore($wpdb);
    $store->mutate($userId, 'smoke:credit', 'accrue', 10000, 0, 900001);
    $store->mutate($userId, 'smoke:credit', 'accrue', 10000, 0, 900001);
    $store->mutate($userId, 'smoke:reserve', 'reserve', -3000, 3000, 900002);
    $store->mutate($userId, 'smoke:spend', 'spend', 0, -3000, 900002);

    $balance = $store->balance($userId);
    if ($balance !== ['available_kopecks' => 7000, 'reserved_kopecks' => 0]) {
        throw new RuntimeException('Unexpected persisted loyalty balance: ' . wp_json_encode($balance));
    }
    if (count($store->history($userId, 20, 0)) !== 3) {
        throw new RuntimeException('Idempotent storage wrote a duplicate ledger entry.');
    }

    echo "Loyalty storage smoke passed\n";
} finally {
    $wpdb->delete($ledgerTable, ['user_id' => $userId], ['%d']);
    $wpdb->delete($accountTable, ['user_id' => $userId], ['%d']);
}

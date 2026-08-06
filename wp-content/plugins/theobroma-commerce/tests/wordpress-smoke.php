<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$pluginFile = dirname(__DIR__) . '/theobroma-commerce.php';
if (!is_file($pluginFile)) {
    throw new RuntimeException('Plugin bootstrap is missing');
}

require_once $pluginFile;

Theobroma\Commerce\Installer::activate();

if (!class_exists(Theobroma\Commerce\Plugin::class)) {
    throw new RuntimeException('Plugin class is not autoloadable');
}

if (!has_action('woocommerce_product_options_sku') || !has_action('woocommerce_admin_process_product_object')) {
    throw new RuntimeException('Ozon product mapping hooks are not registered');
}
if (!has_filter('woocommerce_checkout_fields') || !has_action('woocommerce_after_checkout_validation')) {
    throw new RuntimeException('Provider delivery address hooks are not registered');
}

Theobroma\Commerce\Plugin::boot();
$methods = apply_filters('woocommerce_shipping_methods', []);
foreach (['theobroma_cdek' => 'CDEK', 'theobroma_ozon' => 'Ozon'] as $methodId => $provider) {
    if (!isset($methods[$methodId])) {
        throw new RuntimeException($provider . ' shipping method is not registered');
    }
}

global $wpdb;
foreach (['theobroma_loyalty_accounts', 'theobroma_loyalty_ledger'] as $suffix) {
    $table = $wpdb->prefix . $suffix;
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($exists !== $table) {
        throw new RuntimeException('Loyalty table is missing: ' . $table);
    }
}

$ozonCatalog = (new Theobroma\Commerce\Products\OzonCatalogAudit())->audit(wc_get_products([
    'status' => 'publish',
    'limit' => -1,
    'return' => 'objects',
]));
if ($ozonCatalog['total'] < 1 || $ozonCatalog['mapped'] > $ozonCatalog['total']) {
    throw new RuntimeException('Ozon catalog audit returned an invalid result');
}
$ozonReadiness = (new Theobroma\Commerce\Integrations\Ozon\OzonReadinessFactory())->build(
    (new Theobroma\Commerce\Admin\Settings())->defaults(),
    false,
    wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'objects'])
);
if ($ozonReadiness->status() !== 'awaiting_approval') {
    throw new RuntimeException('Ozon readiness must fail closed with default settings');
}

printf(
    "WordPress commerce smoke passed; Ozon SKU mapped %d/%d\n",
    $ozonCatalog['mapped'],
    $ozonCatalog['total']
);

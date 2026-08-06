<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$pluginFile = dirname(__DIR__) . '/theobroma-commerce.php';
if (!is_file($pluginFile)) {
    throw new RuntimeException('Plugin bootstrap is missing');
}

require_once $pluginFile;

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
if (!isset($methods['theobroma_cdek'])) {
    throw new RuntimeException('CDEK shipping method is not registered');
}

echo "WordPress commerce smoke passed\n";

<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

if (!class_exists('WooCommerce')) {
    fwrite(STDERR, "WooCommerce is not available.\n");
    exit(1);
}

update_option('woocommerce_cod_settings', array(
    'enabled' => 'yes',
    'title' => 'Оплата при получении',
    'description' => 'Оплатите заказ при получении.',
    'instructions' => 'Оплата производится при получении заказа.',
    'enable_for_methods' => array(),
    'enable_for_virtual' => 'yes',
));

echo "WooCommerce checkout settings updated.\n";

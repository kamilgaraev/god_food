<?php
/**
 * Plugin Name: Theobroma Commerce
 * Description: CDEK and Ozon Logistics integration layer for WooCommerce.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Theobroma
 * Text Domain: theobroma-commerce
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('THEOBROMA_COMMERCE_FILE', __FILE__);
define('THEOBROMA_COMMERCE_PATH', plugin_dir_path(__FILE__));
define('THEOBROMA_COMMERCE_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Theobroma\\Commerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = THEOBROMA_COMMERCE_PATH . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
});

register_activation_hook(__FILE__, [Theobroma\Commerce\Installer::class, 'activate']);

add_action('plugins_loaded', [Theobroma\Commerce\Plugin::class, 'boot'], 20);
if (did_action('plugins_loaded')) {
    Theobroma\Commerce\Plugin::boot();
}

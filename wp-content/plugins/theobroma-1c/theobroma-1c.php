<?php
/**
 * Plugin Name: Theobroma 1C
 * Description: Safe CommerceML order exchange for WooCommerce and 1C.
 * Version: 0.2.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Theobroma
 * Text Domain: theobroma-1c
 */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
define('THEOBROMA_1C_FILE', __FILE__);
define('THEOBROMA_1C_PATH', plugin_dir_path(__FILE__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'Theobroma\\OneC\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $file = THEOBROMA_1C_PATH . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) { require_once $file; }
});
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', [Theobroma\OneC\Plugin::class, 'boot'], 20);
register_activation_hook(__FILE__, [Theobroma\OneC\Plugin::class, 'activate']);

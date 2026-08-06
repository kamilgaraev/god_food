<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$hasCdekLifecycle = false;
$hasOzonLifecycle = false;
$hook = $GLOBALS['wp_filter']['woocommerce_order_status_processing'] ?? null;
if ($hook instanceof WP_Hook) {
    foreach ($hook->callbacks as $callbacks) {
        foreach ($callbacks as $callback) {
            $function = $callback['function'] ?? null;
            if (is_array($function) && is_object($function[0]) && $function[0] instanceof Theobroma\Commerce\Orders\CdekOrderLifecycle) {
                $hasCdekLifecycle = true;
            }
            if (is_array($function) && is_object($function[0]) && $function[0] instanceof Theobroma\Commerce\Orders\OzonOrderLifecycle) {
                $hasOzonLifecycle = true;
            }
        }
    }
}
if (!$hasCdekLifecycle || !$hasOzonLifecycle) {
    throw new RuntimeException('Theobroma paid-order provider hooks are not registered');
}

if (!has_action('rest_api_init')) {
    throw new RuntimeException('Provider REST hooks are not registered');
}

do_action('rest_api_init');
$routes = rest_get_server()->get_routes();
if (!isset($routes['/theobroma-commerce/v1/cdek/webhook'])) {
    throw new RuntimeException('CDEK webhook route is not registered');
}

echo "Commerce order hooks smoke passed\n";

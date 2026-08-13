<?php
declare(strict_types=1);
require_once '/var/www/html/wp-load.php';
if (!is_plugin_active('theobroma-1c/theobroma-1c.php')) throw new RuntimeException('Theobroma 1C inactive');
if (!has_action('template_redirect', [Theobroma\OneC\Http\WordPressExchangeEndpoint::class, 'dispatch'])) throw new RuntimeException('Exchange endpoint missing');
if (!has_action('woocommerce_product_options_sku')) throw new RuntimeException('Product identifiers missing');
echo "Theobroma 1C WordPress smoke passed\n";

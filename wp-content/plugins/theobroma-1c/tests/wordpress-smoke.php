<?php
declare(strict_types=1);
require_once '/var/www/html/wp-load.php';
if (!is_plugin_active('theobroma-1c/theobroma-1c.php')) throw new RuntimeException('Theobroma 1C inactive');
if (!has_action('template_redirect', [Theobroma\OneC\Http\WordPressExchangeEndpoint::class, 'dispatch'])) throw new RuntimeException('Exchange endpoint missing');
if (!has_action('woocommerce_product_options_sku')) throw new RuntimeException('Product identifiers missing');
if (!has_action('admin_post_theobroma_1c_download_order_xml')) throw new RuntimeException('Order XML download action missing');
if (has_action('admin_post_nopriv_theobroma_1c_download_order_xml')) throw new RuntimeException('Order XML download must require authentication');
echo "Theobroma 1C WordPress smoke passed\n";

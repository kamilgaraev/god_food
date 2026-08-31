<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$options = array(
    'blogdescription' => 'Натуральный пористый шоколад Theobroma — интернет-магазин «Пища Богов».',
    'timezone_string' => 'Europe/Moscow',
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'start_of_week' => 1,
    'default_comment_status' => 'closed',
    'default_ping_status' => 'closed',
    'comments_notify' => 0,
    'uploads_use_yearmonth_folders' => 1,
    'woocommerce_currency' => 'RUB',
    'woocommerce_default_country' => 'RU',
    'woocommerce_allowed_countries' => 'specific',
    'woocommerce_specific_allowed_countries' => array('RU'),
    'woocommerce_ship_to_countries' => '',
    'woocommerce_weight_unit' => 'kg',
    'woocommerce_dimension_unit' => 'cm',
    'woocommerce_enable_guest_checkout' => 'yes',
    'woocommerce_enable_checkout_login_reminder' => 'yes',
    'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
);

foreach ($options as $name => $value) {
    update_option($name, $value);
    echo $name . '=' . (is_array($value) ? wp_json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value) . PHP_EOL;
}

$page_slugs = array(
    'Рецепты' => 'recipes',
    'Маркетплейсы' => 'marketplace',
    'Где купить' => 'buy',
    'Сотрудничество' => 'cooperation',
    'Пробники шоколада' => 'chocolate-samples',
    'Доставка и оплата' => 'delivery',
);
foreach ($page_slugs as $title => $slug) {
    $matches = get_posts(array('post_type' => 'page', 'post_status' => 'any', 'title' => $title, 'numberposts' => 1));
    if ($matches && $matches[0] instanceof WP_Post && $matches[0]->post_name !== $slug) {
        wp_update_post(array('ID' => $matches[0]->ID, 'post_name' => $slug));
    }
}
$shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
if ($shop_page_id > 0) {
    wp_update_post(array('ID' => $shop_page_id, 'post_name' => 'catalog'));
}

// Early local prototypes had no SKU and duplicate products now managed by sync-catalog.php.
// Move only those unambiguous prototypes to Trash so the operation remains recoverable.
$legacy_products = wc_get_products(array('limit' => -1, 'status' => 'publish', 'sku' => ''));
foreach ($legacy_products as $legacy_product) {
    if ($legacy_product instanceof WC_Product && $legacy_product->get_sku() === '') {
        wp_trash_post($legacy_product->get_id());
        echo 'trashed-legacy-product=' . $legacy_product->get_id() . PHP_EOL;
    }
}

$required_plugins = array(
    'theobroma-admin-tools/theobroma-admin-tools.php',
    'theobroma-analytics/theobroma-analytics.php',
    'theobroma-commerce/theobroma-commerce.php',
    'theobroma-contact-forms/theobroma-contact-forms.php',
    'theobroma-photo-showcases/theobroma-photo-showcases.php',
    'theobroma-1c/theobroma-1c.php',
    'theobroma-seo/theobroma-seo.php',
);
if (is_plugin_active('e-commerce-data-interchange/e-commerce-data-interchange.php')) {
    // The legacy plugin's deactivation hook performs filesystem cleanup and
    // fatals with the ftpsockets transport. WordPress supports silent
    // deactivation specifically for cases where hooks must not be executed.
    deactivate_plugins('e-commerce-data-interchange/e-commerce-data-interchange.php', true);
    echo 'plugin-deactivated=e-commerce-data-interchange/e-commerce-data-interchange.php' . PHP_EOL;
}
foreach ($required_plugins as $plugin) {
    if (!is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }
    echo 'plugin=' . $plugin . PHP_EOL;
}

// Existing media attachments predate the editorial card size. Backfill only
// the missing derivative during explicit environment configuration, never on
// a visitor request.
require_once ABSPATH . 'wp-admin/includes/image.php';
add_image_size('theobroma-media-card', 480, 360, true);
$media_posts = get_posts(array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'category_name' => 'media',
    'numberposts' => -1,
));
foreach ($media_posts as $media_post) {
    $thumbnail_id = get_post_thumbnail_id($media_post);
    $metadata = $thumbnail_id ? wp_get_attachment_metadata($thumbnail_id) : array();
    if (!$thumbnail_id || (is_array($metadata) && !empty($metadata['sizes']['theobroma-media-card']))) {
        continue;
    }
    $updated_metadata = wp_update_image_subsizes($thumbnail_id);
    if (is_wp_error($updated_metadata)) {
        throw new RuntimeException($updated_metadata->get_error_message());
    }
    echo 'media-thumbnail=' . $thumbnail_id . PHP_EOL;
}

require __DIR__ . '/sync-seo.php';

if ((string) get_option('permalink_structure') !== '/%postname%/') {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('/%postname%/');
}
// This explicit setup script is run only during environment configuration,
// so a one-time hard flush is appropriate and also refreshes .htaccess.
flush_rewrite_rules(true);
echo 'permalinks=' . (string) get_option('permalink_structure') . PHP_EOL;

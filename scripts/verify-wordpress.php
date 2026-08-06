<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/** @param mixed $actual */
function verify_value(bool $condition, string $label, $actual = null): void {
    if (!$condition) {
        throw new RuntimeException($label . ($actual === null ? '' : ': ' . print_r($actual, true)));
    }
    echo 'ok: ' . $label . PHP_EOL;
}

verify_value(is_plugin_active('woocommerce/woocommerce.php'), 'WooCommerce active');
verify_value(is_plugin_active('theobroma-admin-tools/theobroma-admin-tools.php'), 'admin tools active');
verify_value(is_plugin_active('theobroma-commerce/theobroma-commerce.php'), 'commerce integrations active');
verify_value(is_plugin_active('theobroma-seo/theobroma-seo.php'), 'SEO active');
verify_value(is_plugin_active('yookassa/yookassa.php'), 'YooKassa active');
$active_plugins = (array) get_option('active_plugins', array());
sort($active_plugins);
verify_value($active_plugins === array(
    'theobroma-admin-tools/theobroma-admin-tools.php',
    'theobroma-commerce/theobroma-commerce.php',
    'theobroma-seo/theobroma-seo.php',
    'woocommerce/woocommerce.php',
    'yookassa/yookassa.php',
), 'only required plugins active', $active_plugins);
verify_value(get_option('permalink_structure') === '/%postname%/', 'permalink structure');
verify_value(get_option('timezone_string') === 'Europe/Moscow', 'timezone');
verify_value((int) get_option('wp_page_for_privacy_policy') > 0, 'privacy page configured');
verify_value((int) get_option('woocommerce_terms_page_id') > 0, 'terms page configured');

$expected_pages = array('catalog', 'recipes', 'marketplace', 'buy', 'cooperation', 'delivery', 'media', 'policy', 'agreement', 'oferta');
foreach ($expected_pages as $slug) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    verify_value($page instanceof WP_Post, 'page /' . $slug . '/');
    verify_value((string) get_post_meta($page->ID, '_theobroma_seo_description', true) !== '', 'SEO description /' . $slug . '/');
}

$products = wc_get_products(array('limit' => -1, 'status' => 'publish'));
if (count($products) !== 27) {
    foreach ($products as $product) {
        echo 'product: ' . $product->get_id() . '|' . $product->get_sku() . '|' . $product->get_name() . PHP_EOL;
    }
}
verify_value(count($products) === 27, '27 published products', count($products));
foreach ($products as $product) {
    $copy = $product->get_meta('_theobroma_detail_copy', true);
    verify_value(is_array($copy) && $copy !== array(), $product->get_sku() . ' copy');
    verify_value((string) $product->get_meta('_theobroma_product_details', true) !== '', $product->get_sku() . ' details');
}

$media_posts = get_posts(array('post_type' => 'post', 'category_name' => 'media', 'post_status' => 'publish', 'numberposts' => -1));
verify_value(count($media_posts) === 4, '4 media posts', count($media_posts));
foreach ($media_posts as $media_post) {
    verify_value(has_post_thumbnail($media_post), $media_post->post_name . ' thumbnail');
    verify_value((string) get_post_meta($media_post->ID, '_theobroma_article_link', true) !== '', $media_post->post_name . ' source link');
}

$recipes = get_posts(array('post_type' => 'theobroma_recipe', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC'));
verify_value(count($recipes) === 3, '3 published recipes', count($recipes));
foreach ($recipes as $recipe) {
    $ingredients = json_decode((string) get_post_meta($recipe->ID, '_theobroma_ingredients', true), true);
    $steps = json_decode((string) get_post_meta($recipe->ID, '_theobroma_steps', true), true);
    verify_value(is_array($ingredients) && $ingredients !== array(), $recipe->post_name . ' ingredients');
    verify_value(is_array($steps) && $steps !== array(), $recipe->post_name . ' steps');
}

$reviews = get_posts(array('post_type' => 'theobroma_review', 'post_status' => 'publish', 'numberposts' => -1));
verify_value(count($reviews) === 7, '7 published homepage reviews', count($reviews));

verify_value(function_exists('theobroma_content'), 'shared content settings available');
verify_value(theobroma_content('shipping_text') !== '', 'shipping text configured');
verify_value(method_exists(Theobroma_Admin_Tools::class, 'render_recipe_box'), 'recipe editor available');
verify_value(method_exists(Theobroma_Admin_Tools::class, 'render_content_settings'), 'shared blocks editor available');
verify_value(class_exists(Theobroma\Seo\WordPressDocumentResolver::class), 'SEO resolver available');
verify_value(class_exists(Theobroma\Seo\SeoMetaBox::class), 'SEO editor available');

wp_set_current_user(1);
ob_start();
Theobroma_Admin_Tools::render_content_hub();
$content_hub = (string) ob_get_clean();
verify_value(str_contains($content_hub, 'Контент сайта') && str_contains($content_hub, 'Товары') && str_contains($content_hub, 'Рецепты') && str_contains($content_hub, 'Общие блоки'), 'content hub renders');

echo 'verification complete' . PHP_EOL;

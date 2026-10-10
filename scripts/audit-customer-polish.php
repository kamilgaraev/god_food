<?php
declare(strict_types=1);
require_once '/var/www/html/wp-load.php';

$products = [];
foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private'], 'orderby' => 'date', 'order' => 'DESC']) as $product) {
    $images = [];
    foreach (array_unique(array_filter(array_merge([$product->get_image_id(), (int) $product->get_meta('_theobroma_product_detail_image_id')], $product->get_gallery_image_ids()))) as $id) {
        $meta = wp_get_attachment_metadata($id) ?: [];
        $original = wp_get_original_image_path($id);
        $dimensions = $original && is_file($original) ? wp_getimagesize($original) : false;
        $images[] = ['id' => $id, 'full' => wp_get_attachment_image_url($id, 'full'), 'original' => wp_get_original_image_url($id), 'dimensions' => [$meta['width'] ?? 0, $meta['height'] ?? 0], 'original_dimensions' => $dimensions ? array_slice($dimensions, 0, 2) : [], 'sizes' => array_intersect_key($meta['sizes'] ?? [], array_flip(['theobroma-product-detail', 'theobroma-product-detail-2x', 'theobroma-product-card', 'large'])), 'source' => get_post_meta($id, '_source_url', true)];
    }
    $products[] = ['id' => $product->get_id(), 'sku' => $product->get_sku(), 'name' => $product->get_name(), 'type' => $product->get_type(), 'status' => $product->get_status(), 'price' => $product->get_price(), 'regular' => $product->get_regular_price(), 'sale' => $product->get_sale_price(), 'created' => $product->get_date_created()?->date('Y-m-d'), 'url' => $product->get_permalink(), 'ozon_sku' => $product->get_meta('_theobroma_ozon_sku'), 'images' => $images];
}
$email = WC()->mailer()->get_emails()['WC_Email_Customer_New_Account'] ?? null;
echo wp_json_encode(['products' => $products, 'shipping_text' => get_option('theobroma_content')['shipping_text'] ?? null, 'email_template' => wc_locate_template('emails/customer-new-account.php'), 'email_class' => $email ? get_class($email) : null, 'theme' => get_stylesheet_directory()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

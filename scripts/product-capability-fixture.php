<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

const THEOBROMA_CAPABILITY_FIXTURE_OPTION = '_theobroma_qa_variable_product_id';

function theobroma_delete_capability_fixture(): void
{
    $productId = (int) get_option(THEOBROMA_CAPABILITY_FIXTURE_OPTION, 0);
    if ($productId > 0) {
        foreach (get_children(['post_parent' => $productId, 'post_type' => 'product_variation', 'fields' => 'ids']) as $variationId) {
            wp_delete_post((int) $variationId, true);
        }
        wp_delete_post($productId, true);
    }
    delete_option(THEOBROMA_CAPABILITY_FIXTURE_OPTION);
}

$action = $argv[1] ?? '';
if ($action === 'cleanup') {
    theobroma_delete_capability_fixture();
    echo "fixture removed\n";
    exit(0);
}
if ($action !== 'create') {
    throw new InvalidArgumentException('Use create or cleanup.');
}

theobroma_delete_capability_fixture();
$imageIds = [];
foreach (wc_get_products(['status' => 'publish', 'limit' => -1]) as $catalogProduct) {
    if ($catalogProduct instanceof WC_Product && $catalogProduct->get_image_id() > 0) {
        $imageIds[] = $catalogProduct->get_image_id();
    }
}
$imageIds = array_slice(array_values(array_unique($imageIds)), 0, 9);
if (count($imageIds) < 9) {
    throw new RuntimeException('Nine catalog images are required for the capability fixture.');
}

$product = new WC_Product_Variable();
$product->set_name('QA variable chocolate');
$product->set_slug('qa-variable-chocolate');
$product->set_status('publish');
$product->set_catalog_visibility('hidden');
$product->set_image_id($imageIds[0]);
$product->set_gallery_image_ids(array_slice($imageIds, 1, 8));
$attribute = new WC_Product_Attribute();
$attribute->set_name('Pack');
$attribute->set_options(['100g', '200g']);
$attribute->set_visible(true);
$attribute->set_variation(true);
$product->set_attributes([$attribute]);
$productId = $product->save();
update_option(THEOBROMA_CAPABILITY_FIXTURE_OPTION, $productId, false);

foreach ([['100g', '100', $imageIds[1]], ['200g', '200', $imageIds[2]]] as [$pack, $price, $imageId]) {
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($productId);
    $variation->set_status('publish');
    $variation->set_attributes(['pack' => $pack]);
    $variation->set_regular_price($price);
    $variation->set_image_id($imageId);
    $variation->set_manage_stock(false);
    $variation->set_stock_status('instock');
    $variation->save();
}

WC_Product_Variable::sync($productId);
wc_delete_product_transients($productId);
echo wp_json_encode([
    'id' => $productId,
    'url' => get_permalink($productId),
    'images' => count($imageIds),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

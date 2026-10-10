<?php
declare(strict_types=1);
require_once '/var/www/html/wp-load.php';

$plan = json_decode(file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$apply = in_array('--apply', $argv, true);
$products = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']]);
if (count($products) !== (int) $plan['product_count']) {
    throw new RuntimeException('Product count changed since the audit. Review before updating.');
}
$backup = ['products' => [], 'content' => get_option('theobroma_content_settings'), 'shipping' => [], 'seo' => []];
$updates = [];
foreach ($plan['updates'] as $row) {
    $product = wc_get_product((int) $row['id']);
    if (!$product || $product->get_sku() !== $row['sku'] || $product->get_name() !== $row['name'] || $product->get_status() !== $row['status']) {
        throw new RuntimeException('Product identity changed: ' . $row['id']);
    }
    if ((float) $product->get_price() === (float) $row['price']) continue;
    if ((float) $product->get_price() !== (float) $row['old_price']) {
        throw new RuntimeException('Price edited since the audit: ' . $row['id']);
    }
    $backup['products'][$product->get_id()] = ['regular' => $product->get_regular_price(), 'sale' => $product->get_sale_price(), 'price' => $product->get_price(), 'sale_from' => $product->get_date_on_sale_from()?->date(DATE_ATOM), 'sale_to' => $product->get_date_on_sale_to()?->date(DATE_ATOM)];
    $updates[] = [$product, $row];
}
$zones = array_merge([new WC_Shipping_Zone(0)], array_map(static fn($zone) => new WC_Shipping_Zone($zone['id']), WC_Shipping_Zones::get_zones()));
foreach ($zones as $zone) {
    foreach ($zone->get_shipping_methods() as $method) {
        if ($method->id === 'free_shipping' && (float) $method->get_option('min_amount') === 2500.0) {
            $key = $method->get_instance_option_key();
            $backup['shipping'][$key] = get_option($key);
        }
    }
}
$delivery = get_page_by_path('delivery');
if ($delivery) $backup['seo'][$delivery->ID] = get_post_meta($delivery->ID, '_theobroma_seo_description', true);
if (!$apply) {
    echo wp_json_encode(['mode' => 'preview', 'prices_to_change' => count($updates), 'products_before' => count($products), 'native_free_shipping_methods' => count($backup['shipping'])], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$backup_path = '/tmp/theobroma-customer-polish-backup-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backup_path, wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new RuntimeException('Cannot save rollback data.');
}
foreach ($updates as [$product, $row]) {
    // The spreadsheet's current price is the selling price; never create an artificial discount.
    $product->set_regular_price($row['price']);
    $product->set_sale_price('');
    $product->set_date_on_sale_from(null);
    $product->set_date_on_sale_to(null);
    $product->save();
    wc_delete_product_transients($product->get_id());
}
$content = (array) get_option('theobroma_content_settings', []);
$content['shipping_text'] = 'Бесплатная доставка от 3000 рублей';
update_option('theobroma_content_settings', $content);
foreach ($backup['shipping'] as $key => $settings) {
    $settings['min_amount'] = '3000';
    update_option($key, $settings);
}
WC_Cache_Helper::get_transient_version('shipping', true);
if ($delivery) {
    update_post_meta($delivery->ID, '_theobroma_seo_description', 'Условия доставки и оплаты заказов Theobroma. Бесплатная доставка от 3 000 ₽, отправка по России и в соседние страны.');
}
foreach ($plan['updates'] as $row) {
    if ((float) wc_get_product((int) $row['id'])->get_price() !== (float) $row['price']) throw new RuntimeException('Price verification failed: ' . $row['id']);
}
$after = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']]);
if (count($after) !== count($products)) throw new RuntimeException('Unexpected product count change.');
echo wp_json_encode(['mode' => 'applied', 'prices_changed' => count($updates), 'prices_verified' => count($plan['updates']), 'products_before' => count($products), 'products_after' => count($after), 'threshold' => \Theobroma\Commerce\Shipping\FreeShippingPolicy::MINIMUM_RUBLES, 'backup' => $backup_path], JSON_PRETTY_PRINT) . PHP_EOL;

<?php
declare(strict_types=1);
require '/var/www/html/wp-load.php';

$plan = json_decode((string) file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$apply = in_array('--apply', $argv, true);
if (get_woocommerce_currency() !== 'RUB') throw new RuntimeException('Expected RUB currency.');
$products = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']]);
if (count($products) !== $plan['product_count']) throw new RuntimeException('Product count changed; review the plan.');
foreach ($plan['prices'] as $row) {
    $product = wc_get_product($row['id']);
    if (!$product || $product->get_sku() !== $row['sku'] || $product->get_status() !== 'publish') throw new RuntimeException('Product identity changed: ' . $row['id']);
    if (!in_array((float) $product->get_price(), [(float) $row['old_price'], (float) $row['price']], true)) throw new RuntimeException('Price was edited after the audit: ' . $row['id']);
    if ((float) $row['price'] !== ceil((float) $row['rrp'] / 10) * 10) throw new RuntimeException('Invalid RRP rounding: ' . $row['id']);
}
foreach ($plan['seo'] as $row) {
    if ($row['kind'] === 'site') continue;
    $record = $row['kind'] === 'term' ? get_term($row['id'], 'product_cat') : get_post($row['id']);
    if (!$record || is_wp_error($record)) throw new RuntimeException('Missing SEO record: ' . $row['id']);
    $slug = $row['kind'] === 'term' ? $record->slug : $record->post_name;
    if ($slug !== $row['slug'] && !($row['id'] === 337 && $slug === 'theobroma-30-strawberry')) throw new RuntimeException('SEO identity changed: ' . $row['id']);
}
$strawberry = wc_get_product(337);
$strawberrySkuId = (int) wc_get_product_id_by_sku('theobroma-30-strawberry');
if (!$strawberry || !in_array($strawberry->get_sku(), ['', 'theobroma-30-strawberry'], true) || !in_array($strawberrySkuId, [0, 337], true)) throw new RuntimeException('Strawberry product identity changed.');
if (!$apply) {
    echo wp_json_encode(['mode' => 'preview', 'price_updates' => count($plan['prices']), 'seo_updates' => count($plan['seo']), 'unpriced_kept' => $plan['unpriced']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}
$keys = ['_theobroma_seo_title', '_theobroma_seo_description', '_theobroma_seo_h1'];
$optionKeys = ['blogname', 'theobroma_seo_home_title', 'theobroma_seo_home_description', 'theobroma_seo_social_image', 'theobroma_seo_redirects'];
$backup = ['products' => [], 'posts' => [], 'terms' => [], 'options' => []];
foreach ($optionKeys as $key) $backup['options'][$key] = get_option($key, null);
foreach ($products as $p) {
    $backup['products'][$p->get_id()] = ['price' => $p->get_price(), 'regular' => $p->get_regular_price(), 'sale' => $p->get_sale_price(),
        'sale_from' => $p->get_date_on_sale_from()?->date(DATE_ATOM), 'sale_to' => $p->get_date_on_sale_to()?->date(DATE_ATOM), 'status' => $p->get_status(), 'sku' => $p->get_sku(), 'slug' => $p->get_slug(), 'image_id' => $p->get_image_id()];
}
foreach ($plan['seo'] as $row) {
    if ($row['kind'] === 'site') continue;
    $values = [];
    foreach ($keys as $key) $values[$key] = $row['kind'] === 'term' ? get_term_meta($row['id'], $key, true) : get_post_meta($row['id'], $key, true);
    if ($row['kind'] === 'term') $backup['terms'][$row['id']] = ['meta' => $values, 'description' => get_term($row['id'])->description];
    else $backup['posts'][$row['id']] = ['meta' => $values, 'post' => get_post($row['id'], ARRAY_A)];
}
$backupPath = '/tmp/theobroma-client-revisions-backup-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backupPath, wp_json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) throw new RuntimeException('Cannot save rollback data.');
foreach ($plan['prices'] as $row) {
    $product = wc_get_product($row['id']);
    $product->set_regular_price($row['price']);
    $product->set_sale_price('');
    $product->set_date_on_sale_from(null);
    $product->set_date_on_sale_to(null);
    $product->save();
    wc_delete_product_transients($row['id']);
}
foreach ($plan['seo'] as $row) {
    if ($row['kind'] === 'site') {
        update_option('theobroma_seo_home_title', $row['title']);
        update_option('theobroma_seo_home_description', $row['description']);
        continue;
    }
    foreach (array_combine($keys, [$row['title'], $row['description'], $row['h1']]) as $key => $value) {
        if ($row['kind'] === 'term') update_term_meta($row['id'], $key, $value);
        else update_post_meta($row['id'], $key, $value);
    }
    if ($row['kind'] === 'term') {
        $result = wp_update_term($row['id'], 'product_cat', ['description' => $row['description']]);
        if (is_wp_error($result)) throw new RuntimeException($result->get_error_message());
    } elseif (in_array(get_post_type($row['id']), ['post', 'theobroma_recipe'], true)) {
        $result = wp_update_post(['ID' => $row['id'], 'post_title' => $row['h1'], 'post_excerpt' => $row['description']], true);
        if (is_wp_error($result)) throw new RuntimeException($result->get_error_message());
    }
}
$strawberry = wc_get_product(337);
if (!$strawberry || !in_array($strawberry->get_sku(), ['', 'theobroma-30-strawberry'], true)) throw new RuntimeException('Strawberry product identity changed.');
$strawberry->set_sku('theobroma-30-strawberry');
$strawberry->set_slug('theobroma-30-strawberry');
$strawberry->set_short_description('С клубникой');
$strawberry->save();
$cacao = wc_get_product(82);
if ($cacao && !$cacao->get_image_id()) {
    $detailImage = (int) $cacao->get_meta('_theobroma_product_detail_image_id');
    if ($detailImage && wp_attachment_is_image($detailImage)) {
        $cacao->set_image_id($detailImage);
        $cacao->save();
    }
}
$chiaId = wc_get_product_id_by_sku('theobroma-chia-100');
if ($chiaId) { $chia = wc_get_product($chiaId); $chia->set_status('draft'); $chia->save(); }
update_option('blogname', 'Theobroma Пища богов');
update_option('theobroma_seo_social_image', home_url('/wp-content/themes/theobroma/assets/images/social-preview-20261002.png', 'https'));
update_option('theobroma_seo_redirects', array_merge((array) get_option('theobroma_seo_redirects', []), $plan['redirects']));
foreach ($plan['prices'] as $row) {
    if ((float) wc_get_product($row['id'])->get_price() !== (float) $row['price']) throw new RuntimeException('Price verification failed: ' . $row['id']);
}
foreach ($plan['unpriced'] as $row) {
    if ((float) wc_get_product($row['id'])->get_price() !== (float) $row['price']) throw new RuntimeException('Unlisted product price changed: ' . $row['id']);
}
if (count(wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']])) !== $plan['product_count']) throw new RuntimeException('Unexpected product creation or removal.');
echo wp_json_encode(['mode' => 'applied', 'prices_verified' => count($plan['prices']), 'seo_updated' => count($plan['seo']), 'unlisted_prices_preserved' => count($plan['unpriced']), 'products_total' => $plan['product_count'], 'backup' => $backupPath], JSON_PRETTY_PRINT) . PHP_EOL;

<?php
declare(strict_types=1);
require __DIR__ . '/sync-ozon-catalog.php';
$rows = ozon_catalog_read($argv[1]);
$plans = array_map('ozon_catalog_plan', $rows);
$check = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$check(count($plans) === 61, 'Expected 61 source products');
$check(count(array_unique(array_column($plans, 'sku'))) === 61, 'Duplicate SKU');
$check(count(array_filter($plans, fn($p) => $p['shipping'] !== null)) === 17, 'Expected 17 valid shipping records');
$check(count(array_filter($plans, fn($p) => $p['net_g'] !== null)) === 60, 'Expected 60 net weights');
$byArticle = array_column($plans, null, 'article');
$check($byArticle['МШнКМ30']['net_g'] === 90, 'Multipack must weigh 90 g');
$check($byArticle['МШнКМ30']['shipping'] === null, 'Reject gross below net');
$check($byArticle['МШнКМ30_1']['sku'] === 'theobroma-30-date-powder', 'Keep matched SKU');
$check($byArticle['МШнКМ30_1']['ean'] === '', 'OZN barcode is not EAN');
$check($byArticle['МШнКозМ30_15']['net_g'] === 450, 'Showbox must weigh 450 g');
$check($byArticle['Пакет_подарочный']['net_g'] === null, 'Do not invent bag weight');
echo "PASS: 61 records, unique SKU, pack sizes, shipping conflict, EAN and matching\n";
if (in_array('--runtime', $argv, true)) {
    require_once '/var/www/html/wp-load.php';
    $ids = [];
    foreach ($plans as $plan) {
        $product = wc_get_product(wc_get_product_id_by_sku($plan['sku']));
        $check($product instanceof WC_Product, 'Missing product: '.$plan['sku']);
        $ids[] = $product->get_id();
        $check($product->get_status() === 'publish', 'Product is not published');
        $check((string) $product->get_meta('_theobroma_ozon_sku') === $plan['ozon'], 'Ozon SKU mismatch');
        $check((float) $product->get_regular_price() === (float) $plan['price'], 'Price mismatch');
        $check($product->get_meta('_theobroma_ozon_report') === $plan['source'], 'Incomplete source metadata');
        if ($plan['shipping'] !== null) {
            $check(abs((float) $product->get_weight() - wc_get_weight($plan['shipping'][3], get_option('woocommerce_weight_unit'), 'g')) < 0.00001, 'Weight unit mismatch');
        }
    }
    $check(count(array_unique($ids)) === 61, 'Duplicate target');
    $preview = ozon_catalog_apply($plans, false);
    $check($preview['created'] === 0 && $preview['updated'] === 61, 'Repeat would create duplicates');
    echo 'PASS runtime: 61 published source products, all prices/metadata, 17 shipping weights; repeat creates 0. Total products: '.count(wc_get_products(['limit'=>-1,'status'=>['publish','draft','private']]))."\n";
}

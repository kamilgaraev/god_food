<?php
declare(strict_types=1);

final class WC_Product {
    public function __construct(
        private int $id,
        private string $name,
        private string $sku,
        private string $price,
        private int $menuOrder = 0,
        private bool $inStock = true,
        private string $status = 'publish',
        private bool $visible = true,
    ) {
    }

    public function get_id(): int { return $this->id; }
    public function get_name(): string { return $this->name; }
    public function get_sku(): string { return $this->sku; }
    public function get_price(): string { return $this->price; }
    public function get_menu_order(): int { return $this->menuOrder; }
    public function is_in_stock(): bool { return $this->inStock; }
    public function get_status(): string { return $this->status; }
    public function is_visible(): bool { return $this->visible; }
}

function wc_get_page_permalink(string $page): string {
    return $page === 'shop' ? 'https://example.test/catalog/' : 'https://example.test/';
}

function add_query_arg(string $key, string|int $value, string $url): string {
    return $url . '?' . rawurlencode($key) . '=' . rawurlencode((string) $value);
}

$test_products_by_sku = array();
$test_catalog_products = array();

function wc_get_product_id_by_sku(string $sku): int {
    global $test_products_by_sku;
    return isset($test_products_by_sku[$sku]) ? $test_products_by_sku[$sku]->get_id() : 0;
}

function wc_get_product(int $id): ?WC_Product {
    global $test_products_by_sku;
    foreach ($test_products_by_sku as $product) {
        if ($product->get_id() === $id) {
            return $product;
        }
    }
    return null;
}

function wc_get_products(array $args): array {
    global $test_catalog_products;
    return $test_catalog_products;
}

function assert_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$module = dirname(__DIR__) . '/wp-content/themes/theobroma/inc/homepage.php';
if (!is_file($module)) {
    throw new RuntimeException('Homepage data module is not implemented');
}
require_once $module;

assert_same(70, theobroma_normalize_cacao_percentage('70'), 'Accepts an allowed percentage');
assert_same(80, theobroma_normalize_cacao_percentage(80), 'Accepts an allowed integer');
assert_same(null, theobroma_normalize_cacao_percentage('70foo'), 'Rejects a malformed percentage');
assert_same(null, theobroma_normalize_cacao_percentage('55'), 'Rejects an unsupported percentage');
assert_same(68, theobroma_product_cacao_percentage(new WC_Product(1, '68% горький шоколад 100г', 'theobroma-100-68-coriander', '772')), 'Reads percentage from canonical product title');
assert_same(null, theobroma_product_cacao_percentage(new WC_Product(2, 'Молочный шоколад 100г', 'theobroma-100-cow', '674')), 'Ignores products without cacao percentage');

$products = array(
    new WC_Product(10, '70% горький шоколад 30г', 'theobroma-30-70', '225', 2),
    new WC_Product(11, '70% горький шоколад 200г', 'theobroma-200-70', '1418', 0),
    new WC_Product(12, '70% горький шоколад 100г', 'theobroma-100-70', '768', 1),
    new WC_Product(13, '80% горький шоколад 30г', 'theobroma-30-80', '225', 0),
    new WC_Product(14, '59% горький шоколад 30г', 'theobroma-30-59-date', '200', 0, false),
    new WC_Product(15, 'Молочный шоколад 100г', 'theobroma-100-cow', '674', 0),
    new WC_Product(16, '65% скрытый шоколад 100г', 'theobroma-100-65-hidden', '700', 0, true, 'publish', false),
    new WC_Product(17, '68% шоколад без цены 100г', 'theobroma-100-68-empty', '', 0),
);

$groups = theobroma_group_cacao_products($products);
assert_same(array(59, 70, 80), array_keys($groups), 'Groups only supported cacao products in numeric order');
assert_same(12, $groups[70]['representative']->get_id(), 'Prefers a 100g representative over 200g and 30g');
assert_same(225.0, $groups[70]['minimum_price'], 'Uses the lowest group price');
assert_same(array(10, 11, 12), array_map(static fn(WC_Product $product): int => $product->get_id(), $groups[70]['products']), 'Keeps every matching product in the group');
assert_same(array(10, 11, 12), theobroma_cacao_filter_product_ids($products, '70'), 'Returns every product matching the requested percentage');
assert_same(array(14), theobroma_cacao_filter_product_ids($products, 59), 'Keeps matching products even when currently out of stock');
assert_same(null, theobroma_cacao_filter_product_ids($products, '99'), 'Ignores an unsupported catalogue filter');
assert_same('https://example.test/catalog/?cacao_percentage=80', theobroma_cacao_catalog_url(80), 'Builds the public catalogue filter URL');
assert_same('https://example.test/catalog/', theobroma_cacao_catalog_url(55), 'Falls back to the catalogue for unsupported values');
assert_same(array(59, 65, 68, 70, 80), theobroma_cacao_title_prefixes(), 'Builds only supported canonical title prefixes');

$test_products_by_sku = array(
    'theobroma-100-70' => new WC_Product(20, '70% горький шоколад 100г', 'theobroma-100-70', '768'),
    'theobroma-30-raspberry' => new WC_Product(21, 'Молочный шоколад 30г', 'theobroma-30-raspberry', '220'),
    'theobroma-cacao-200' => new WC_Product(22, 'Какао порошок натуральный', 'theobroma-cacao-200', '567'),
    'theobroma-100-80' => new WC_Product(23, '80% горький шоколад 100г', 'theobroma-100-80', '814'),
);
assert_same(array(20, 21, 22, 23), array_map(static fn(WC_Product $product): int => $product->get_id(), theobroma_homepage_products()), 'Loads curated homepage products in editorial order');
$test_products_by_sku['theobroma-100-70'] = new WC_Product(24, '70% скрытый шоколад 100г', 'theobroma-100-70', '768', 0, true, 'draft');
assert_same(array(21, 22, 23), array_map(static fn(WC_Product $product): int => $product->get_id(), theobroma_homepage_products()), 'Does not expose unpublished curated products');

$test_catalog_products = $products;
assert_same(array(59, 70, 80), array_keys(theobroma_home_cacao_groups()), 'Builds homepage cacao groups from the WooCommerce catalogue');
assert_same(80, theobroma_requested_cacao_percentage(array('cacao_percentage' => '80')), 'Reads a supported public filter value');
assert_same(null, theobroma_requested_cacao_percentage(array('cacao_percentage' => '80x')), 'Ignores malformed public filter values');
assert_same(array(10, 11, 12), theobroma_catalog_percentage_product_ids(70), 'Builds catalogue post IDs for the requested percentage');

echo "Homepage data contract verified\n";

<?php
declare(strict_types=1);

defined('ABSPATH') || (PHP_SAPI === 'cli') || exit;

/** @return int[] */
function theobroma_allowed_cacao_percentages(): array {
    return array(59, 65, 68, 70, 80);
}

function theobroma_normalize_cacao_percentage(mixed $value): ?int {
    if (is_int($value)) {
        $percentage = $value;
    } elseif (is_string($value) && preg_match('/^\d{2,3}$/D', trim($value)) === 1) {
        $percentage = (int) trim($value);
    } else {
        return null;
    }

    return in_array($percentage, theobroma_allowed_cacao_percentages(), true) ? $percentage : null;
}

function theobroma_product_cacao_percentage(WC_Product $product): ?int {
    if (preg_match('/^\s*(\d{2,3})\s*%/u', $product->get_name(), $matches) !== 1) {
        return null;
    }

    return theobroma_normalize_cacao_percentage($matches[1]);
}

function theobroma_product_size_preference(WC_Product $product): int {
    if (preg_match('/theobroma-(30|100|200)-/', $product->get_sku(), $matches) !== 1) {
        return 99;
    }

    return array_search((int) $matches[1], array(100, 200, 30), true);
}

function theobroma_product_is_home_eligible(WC_Product $product, bool $require_price = false): bool {
    if ($product->get_status() !== 'publish' || !$product->is_visible()) {
        return false;
    }
    return !$require_price || $product->get_price() !== '';
}

/** @return int[] */
function theobroma_cacao_title_prefixes(): array {
    return theobroma_allowed_cacao_percentages();
}

/**
 * @param WC_Product[] $products
 * @return array<int,array{products:WC_Product[],representative:WC_Product,minimum_price:float}>
 */
function theobroma_group_cacao_products(array $products): array {
    $groups = array();
    foreach ($products as $product) {
        if (!$product instanceof WC_Product || !theobroma_product_is_home_eligible($product, true)) {
            continue;
        }
        $percentage = theobroma_product_cacao_percentage($product);
        if ($percentage === null) {
            continue;
        }
        $groups[$percentage][] = $product;
    }

    ksort($groups, SORT_NUMERIC);
    foreach ($groups as $percentage => $group_products) {
        $ranked = $group_products;
        usort($ranked, static function (WC_Product $left, WC_Product $right): int {
            $stock_rank = (int) !$left->is_in_stock() <=> (int) !$right->is_in_stock();
            if ($stock_rank !== 0) {
                return $stock_rank;
            }
            $size_rank = theobroma_product_size_preference($left) <=> theobroma_product_size_preference($right);
            if ($size_rank !== 0) {
                return $size_rank;
            }
            $menu_rank = $left->get_menu_order() <=> $right->get_menu_order();
            return $menu_rank !== 0 ? $menu_rank : $left->get_id() <=> $right->get_id();
        });
        $prices = array_map(static fn(WC_Product $product): float => (float) $product->get_price(), $group_products);
        $groups[$percentage] = array(
            'products' => $group_products,
            'representative' => $ranked[0],
            'minimum_price' => min($prices),
        );
    }

    return $groups;
}

/**
 * @param WC_Product[] $products
 * @return int[]|null Null means that no supported filter was requested.
 */
function theobroma_cacao_filter_product_ids(array $products, mixed $percentage): ?array {
    $normalized = theobroma_normalize_cacao_percentage($percentage);
    if ($normalized === null) {
        return null;
    }

    $ids = array();
    foreach ($products as $product) {
        if ($product instanceof WC_Product && theobroma_product_cacao_percentage($product) === $normalized) {
            $ids[] = $product->get_id();
        }
    }
    return $ids;
}

function theobroma_cacao_catalog_url(mixed $percentage): string {
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    $normalized = theobroma_normalize_cacao_percentage($percentage);
    return $normalized === null ? $shop_url : add_query_arg('cacao_percentage', $normalized, $shop_url);
}

/** @return WC_Product[] */
function theobroma_homepage_products(): array {
    if (!function_exists('wc_get_product_id_by_sku')) {
        return array();
    }

    $products = array();
    foreach (array('theobroma-100-70', 'theobroma-30-raspberry', 'theobroma-cacao-200', 'theobroma-100-80') as $sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        $product = $product_id ? wc_get_product($product_id) : null;
        if ($product instanceof WC_Product && theobroma_product_is_home_eligible($product)) {
            $products[] = $product;
        }
    }
    return $products;
}

/** @return array<int,array{products:WC_Product[],representative:WC_Product,minimum_price:float}> */
function theobroma_home_cacao_groups(): array {
    if (!function_exists('wc_get_products')) {
        return array();
    }

    if (function_exists('get_posts')) {
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => -1,
            'suppress_filters' => false,
            'fields' => 'ids',
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'tax_query' => array(array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => array('chocolate-200g', 'chocolate-100g', 'chocolate-30g'),
            )),
            'theobroma_cacao_percentages' => theobroma_allowed_cacao_percentages(),
        ));
        $products = array_filter(array_map('wc_get_product', $product_ids));
    } else {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'category' => array('chocolate-200g', 'chocolate-100g', 'chocolate-30g'),
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
    }
    return theobroma_group_cacao_products(is_array($products) ? $products : array());
}

function theobroma_cacao_posts_where(string $where, WP_Query $query): string {
    $requested = $query->get('theobroma_cacao_percentages');
    $requested = is_array($requested) ? $requested : array($requested);
    $percentages = array_values(array_filter(array_map('theobroma_normalize_cacao_percentage', $requested), static fn(?int $value): bool => $value !== null));
    if (!$percentages) {
        return $where;
    }

    global $wpdb;
    $clauses = array_map(
        static fn(int $percentage): string => $wpdb->prepare($wpdb->posts . '.post_title LIKE %s', $wpdb->esc_like($percentage . '%') . '%'),
        array_unique($percentages)
    );
    return $where . ' AND (' . implode(' OR ', $clauses) . ')';
}

if (function_exists('add_filter')) {
    add_filter('posts_where', 'theobroma_cacao_posts_where', 10, 2);
}

/** @return array<int,array{label:string,description:string}> */
function theobroma_cacao_profiles(): array {
    return array(
        59 => array('label' => 'мягкий', 'description' => 'Мягкий шоколад с ягодными, ореховыми и фруктовыми сочетаниями.'),
        65 => array('label' => 'пряный', 'description' => 'Тёплый вкус какао с выразительной нотой натуральной корицы.'),
        68 => array('label' => 'характерный', 'description' => 'Чистый шоколадный вкус, раскрытый тонким ароматом кориандра.'),
        70 => array('label' => 'классический', 'description' => 'Баланс насыщенного какао и деликатной сладости кокосового сахара.'),
        80 => array('label' => 'глубокий', 'description' => 'Глубокий, строгий вкус с долгим шоколадным послевкусием.'),
    );
}

/**
 * @param array<int,array{products:WC_Product[],representative:WC_Product,minimum_price:float}> $groups
 * @param array<int,array{label:string,description:string}> $profiles
 * @return array<int,array{percentage:int,label:string,group:array{products:WC_Product[],representative:WC_Product,minimum_price:float}}>
 */
function theobroma_home_cacao_options(array $groups, array $profiles): array {
    ksort($groups, SORT_NUMERIC);

    $options = array();
    foreach ($groups as $percentage => $group) {
        $percentage = (int) $percentage;
        $options[$percentage] = array(
            'percentage' => $percentage,
            'label' => $profiles[$percentage]['label'] ?? '',
            'group' => $group,
        );
    }

    return $options;
}

function theobroma_requested_cacao_percentage(?array $source = null): ?int {
    $source ??= $_GET;
    return theobroma_normalize_cacao_percentage($source['cacao_percentage'] ?? null);
}

/** @return int[] */
function theobroma_catalog_percentage_product_ids(int $percentage): array {
    if (!function_exists('wc_get_products')) {
        return array();
    }
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        'category' => array('chocolate-200g', 'chocolate-100g', 'chocolate-30g'),
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));
    return theobroma_cacao_filter_product_ids(is_array($products) ? $products : array(), $percentage) ?? array();
}

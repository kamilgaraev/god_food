<?php
declare(strict_types=1);

defined('ABSPATH') || (PHP_SAPI === 'cli') || exit;

/** @return array<int,array{enabled:bool,label:string,description:string,product_id:int}> */
function theobroma_cacao_default_profiles(): array {
    return array(
        59 => array('enabled' => true, 'label' => 'мягкий', 'description' => 'Мягкий шоколад с ягодными, ореховыми и фруктовыми сочетаниями.', 'product_id' => 0),
        65 => array('enabled' => true, 'label' => 'пряный', 'description' => 'Тёплый вкус какао с выразительной нотой натуральной корицы.', 'product_id' => 0),
        68 => array('enabled' => true, 'label' => 'характерный', 'description' => 'Чистый шоколадный вкус, раскрытый тонким ароматом кориандра.', 'product_id' => 0),
        70 => array('enabled' => true, 'label' => 'классический', 'description' => 'Баланс насыщенного какао и деликатной сладости кокосового сахара.', 'product_id' => 0),
        80 => array('enabled' => true, 'label' => 'глубокий', 'description' => 'Глубокий, строгий вкус с долгим шоколадным послевкусием.', 'product_id' => 0),
    );
}

/** @return array<string,string> */
function theobroma_cacao_default_settings(): array {
    $settings = array(
        'cacao_enabled' => '1',
        'cacao_heading' => 'Ваш процент какао',
        'cacao_intro' => 'От {min}% до {max}%. Выберите крепость, а мы подберем вкус, идеально подходящий вам.',
        'cacao_button_label' => 'Купить',
        'cacao_default_percentage' => '70',
    );
    foreach (theobroma_cacao_default_profiles() as $percentage => $profile) {
        $prefix = 'cacao_' . $percentage . '_';
        $settings[$prefix . 'enabled'] = $profile['enabled'] ? '1' : '0';
        $settings[$prefix . 'label'] = $profile['label'];
        $settings[$prefix . 'description'] = $profile['description'];
    }
    return $settings;
}

/** @param array<int,mixed> $rows @return array<int,array{enabled:bool,label:string,description:string,product_id:int}> */
function theobroma_normalize_cacao_profiles(array $rows): array {
    $profiles = array();
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $raw_percentage = $row['percentage'] ?? null;
        if (is_int($raw_percentage)) {
            $percentage = $raw_percentage;
        } elseif (is_string($raw_percentage) && preg_match('/^\d{1,3}$/D', trim($raw_percentage)) === 1) {
            $percentage = (int) trim($raw_percentage);
        } else {
            continue;
        }
        $label = is_string($row['label'] ?? null) ? trim(strip_tags($row['label'])) : '';
        if ($percentage < 1 || $percentage > 100 || $label === '' || isset($profiles[$percentage])) {
            continue;
        }
        $description = is_string($row['description'] ?? null) ? trim(strip_tags($row['description'])) : '';
        $enabled = in_array($row['enabled'] ?? '0', array('1', 1, true), true);
        $product_id = is_int($row['product_id'] ?? null) || (is_string($row['product_id'] ?? null) && ctype_digit($row['product_id']))
            ? max(0, (int) $row['product_id'])
            : 0;
        $image_url = is_string($row['image_url'] ?? null) ? trim($row['image_url']) : '';
        $fact = is_string($row['fact'] ?? null) ? trim(strip_tags($row['fact'])) : '';
        $profiles[$percentage] = array('enabled' => $enabled, 'label' => $label, 'description' => $description, 'product_id' => $product_id, 'image_url' => $image_url, 'fact' => $fact);
    }
    ksort($profiles, SORT_NUMERIC);
    return $profiles;
}

/**
 * @return array{enabled:bool,heading:string,intro:string,button_label:string,default_percentage:int,profiles:array<int,array{enabled:bool,label:string,description:string,product_id:int}>}
 */
function theobroma_cacao_settings(): array {
    $defaults = theobroma_cacao_default_settings();
    $saved = function_exists('get_option') ? get_option('theobroma_content_settings', array()) : array();
    $saved = is_array($saved) ? $saved : array();
    $values = $defaults;
    foreach ($defaults as $key => $default) {
        if (isset($saved[$key]) && is_string($saved[$key]) && $saved[$key] !== '') {
            $values[$key] = $saved[$key];
        }
    }

    if (isset($saved['cacao_profiles']) && is_array($saved['cacao_profiles'])) {
        $profile_rows = $saved['cacao_profiles'];
    } else {
        $profile_rows = array();
        foreach (theobroma_cacao_default_profiles() as $percentage => $profile) {
            $prefix = 'cacao_' . $percentage . '_';
            $profile_rows[] = array(
                'percentage' => $percentage,
                'enabled' => $values[$prefix . 'enabled'],
                'label' => $values[$prefix . 'label'],
                'description' => $values[$prefix . 'description'],
                'product_id' => 0,
            );
        }
    }
    $profiles = theobroma_normalize_cacao_profiles($profile_rows);

    $raw_default = $values['cacao_default_percentage'];
    $default_percentage = preg_match('/^\d{1,3}$/D', $raw_default) === 1 ? (int) $raw_default : 0;
    if (!isset($profiles[$default_percentage])) {
        $default_percentage = isset($profiles[70]) ? 70 : (int) (array_key_first($profiles) ?? 0);
    }
    return array(
        'enabled' => $values['cacao_enabled'] === '1',
        'heading' => $values['cacao_heading'],
        'intro' => $values['cacao_intro'],
        'button_label' => $values['cacao_button_label'],
        'default_percentage' => $default_percentage,
        'profiles' => $profiles,
    );
}

/** @return int[] */
function theobroma_allowed_cacao_percentages(): array {
    return array_keys(theobroma_cacao_settings()['profiles']);
}

function theobroma_normalize_cacao_percentage(mixed $value): ?int {
    if (is_int($value)) {
        $percentage = $value;
    } elseif (is_string($value) && preg_match('/^\d{1,3}$/D', trim($value)) === 1) {
        $percentage = (int) trim($value);
    } else {
        return null;
    }

    return in_array($percentage, theobroma_allowed_cacao_percentages(), true) ? $percentage : null;
}

function theobroma_product_cacao_percentage(WC_Product $product): ?int {
    if (preg_match('/^\s*(\d{1,3})\s*%/u', $product->get_name(), $matches) !== 1) {
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
    if (!function_exists('wc_get_product_id_by_sku') || !function_exists('wc_get_product')) {
        return array();
    }

    $products = array();
    $seen_product_ids = array();
    $product_ids = array();
    $settings = function_exists('get_option') ? get_option('theobroma_content_settings', array()) : array();
    $settings = is_array($settings) ? $settings : array();

    for ($slot = 1; $slot <= 4; $slot++) {
        $value = $settings['homepage_product_' . $slot] ?? '';
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
            $product_ids[] = (int) $value;
        }
    }

    $append_product = static function (mixed $product) use (&$products, &$seen_product_ids): void {
        if (!$product instanceof WC_Product || !theobroma_product_is_home_eligible($product)) {
            return;
        }
        $product_id = $product->get_id();
        if (isset($seen_product_ids[$product_id])) {
            return;
        }
        $products[] = $product;
        $seen_product_ids[$product_id] = true;
    };

    foreach ($product_ids as $product_id) {
        $append_product(wc_get_product($product_id));
    }
    if (count($products) === 4) {
        return $products;
    }

    foreach (array('theobroma-100-70', 'theobroma-30-raspberry', 'theobroma-cacao-200', 'theobroma-100-80') as $sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        $append_product($product_id ? wc_get_product($product_id) : null);
        if (count($products) === 4) {
            return $products;
        }
    }

    if (function_exists('wc_get_products')) {
        $catalog_products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'return' => 'objects',
        ));
        foreach (is_array($catalog_products) ? $catalog_products : array() as $product) {
            $append_product($product instanceof WC_Product ? $product : null);
            if (count($products) === 4) {
                break;
            }
        }
    }

    return array_slice($products, 0, 4);
}

/** @return array<int,array{products:WC_Product[],representative:WC_Product,minimum_price:float,url?:string}> */
function theobroma_home_cacao_groups(): array {
    if (!function_exists('wc_get_products') && !function_exists('wc_get_product')) {
        return array();
    }

    $products = array();
    if (function_exists('get_posts') && function_exists('wc_get_product')) {
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
    } elseif (function_exists('wc_get_products')) {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'category' => array('chocolate-200g', 'chocolate-100g', 'chocolate-30g'),
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
    }
    $groups = theobroma_group_cacao_products(is_array($products) ? $products : array());
    if (function_exists('wc_get_product')) {
        foreach (theobroma_cacao_settings()['profiles'] as $percentage => $profile) {
            $product_id = (int) ($profile['product_id'] ?? 0);
            if ($product_id < 1) {
                continue;
            }
            $product = wc_get_product($product_id);
            if (!$product instanceof WC_Product || !theobroma_product_is_home_eligible($product, true)) {
                continue;
            }
            $groups[(int) $percentage] = array(
                'products' => array($product),
                'representative' => $product,
                'minimum_price' => (float) $product->get_price(),
                'url' => $product->get_permalink(),
            );
        }
    }
    ksort($groups, SORT_NUMERIC);
    return $groups;
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
    return array_map(
        static fn(array $profile): array => array('label' => $profile['label'], 'description' => $profile['description'], 'image_url' => $profile['image_url'] ?? '', 'fact' => $profile['fact'] ?? ''),
        theobroma_cacao_settings()['profiles']
    );
}

/** @return int[] */
function theobroma_enabled_cacao_percentages(): array {
    return array_keys(array_filter(
        theobroma_cacao_settings()['profiles'],
        static fn(array $profile): bool => $profile['enabled']
    ));
}

function theobroma_cacao_intro(int $minimum, int $maximum): string {
    return strtr(theobroma_cacao_settings()['intro'], array(
        '{min}' => (string) $minimum,
        '{max}' => (string) $maximum,
    ));
}

/** @param array<int,mixed> $available */
function theobroma_home_cacao_default_percentage(array $available): int {
    if (!$available) {
        return 0;
    }
    $configured = theobroma_cacao_settings()['default_percentage'];
    return isset($available[$configured]) ? $configured : (int) array_key_first($available);
}

/**
 * @param array<int,array{products:WC_Product[],representative:WC_Product,minimum_price:float,url?:string}> $groups
 * @param array<int,array{label:string,description:string}> $profiles
 * @return array<int,array{percentage:int,label:string,group:array{products:WC_Product[],representative:WC_Product,minimum_price:float,url?:string}}>
 */
function theobroma_home_cacao_options(array $groups, array $profiles): array {
    ksort($groups, SORT_NUMERIC);
    $enabled = theobroma_enabled_cacao_percentages();

    $options = array();
    foreach ($groups as $percentage => $group) {
        $percentage = (int) $percentage;
        if (!in_array($percentage, $enabled, true)) {
            continue;
        }
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

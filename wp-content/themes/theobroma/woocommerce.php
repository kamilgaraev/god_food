<?php
if (function_exists('is_product') && is_product()) {
    require get_template_directory() . '/woocommerce/single-product.php';
    return;
}
get_header();
$is_catalog = function_exists('is_shop') && (is_shop() || is_product_category());
$catalog_categories = $is_catalog ? theobroma_catalog_categories() : array();
$catalog_default_slug = theobroma_catalog_default_slug($catalog_categories);
$catalog_request = wp_unslash($_GET['product_group'] ?? '');
if ($catalog_request === '' && function_exists('is_product_category') && is_product_category()) {
    $catalog_term = get_queried_object();
    $catalog_request = $catalog_term instanceof WP_Term ? $catalog_term->slug : '';
}
$catalog_group = sanitize_key($catalog_request !== '' ? $catalog_request : $catalog_default_slug);
$catalog_slugs = wp_list_pluck($catalog_categories, 'slug');
if (!in_array($catalog_group, $catalog_slugs, true)) {
    $catalog_group = $catalog_default_slug;
}
$shop_url = wc_get_page_permalink('shop');
$cacao_percentage = $is_catalog ? theobroma_requested_cacao_percentage() : null;
?>
<main class="shop-page<?php echo $is_catalog ? ' catalog-page catalog-group-' . esc_attr($catalog_group) : ''; ?>"><div class="shop-shell">
    <?php if ($is_catalog) : ?>
        <nav class="catalog-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Продукция Пища богов</strong></nav>
        <h1 class="catalog-title">Продукция Пища богов</h1>
        <nav class="catalog-filters" aria-label="Категории товаров">
            <?php foreach ($catalog_categories as $catalog_category) :
                $category_slug = $catalog_category->slug;
                $is_active = $catalog_group === $category_slug;
                $category_url = $category_slug === $catalog_default_slug
                    ? $shop_url
                    : add_query_arg('product_group', $category_slug, $shop_url);
                ?>
                <a class="<?php echo $is_active ? 'is-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url($category_url); ?>"><?php echo esc_html($catalog_category->name); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($cacao_percentage !== null) : ?>
            <div class="catalog-cacao-filter" role="status"><span>Какао: <strong><?php echo esc_html((string) $cacao_percentage); ?>%</strong></span><a href="<?php echo esc_url($shop_url); ?>">Сбросить фильтр</a></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php woocommerce_content(); ?>
</div></main>
<?php get_footer();

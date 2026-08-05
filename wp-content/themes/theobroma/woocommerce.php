<?php
if (function_exists('is_product') && is_product()) {
    require get_template_directory() . '/woocommerce/single-product.php';
    return;
}
get_header();
$is_catalog = function_exists('is_shop') && (is_shop() || is_product_category());
$catalog_group = sanitize_key(wp_unslash($_GET['product_group'] ?? 'chocolate-200g'));
$shop_url = wc_get_page_permalink('shop');
?>
<main class="shop-page<?php echo $is_catalog ? ' catalog-page catalog-group-' . esc_attr($catalog_group) : ''; ?>"><div class="shop-shell">
    <?php if ($is_catalog) : ?>
        <nav class="catalog-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Каталог</strong></nav>
        <h1 class="catalog-title">Каталог</h1>
        <nav class="catalog-filters" aria-label="Категории товаров"><a class="<?php echo $catalog_group === 'chocolate-200g' ? 'is-active' : ''; ?>" href="<?php echo esc_url($shop_url); ?>">Шоколад 200г</a><a class="<?php echo $catalog_group === 'chocolate-100g' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('product_group', 'chocolate-100g', $shop_url)); ?>">Шоколад 100г</a><a class="<?php echo $catalog_group === 'chocolate-30g' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('product_group', 'chocolate-30g', $shop_url)); ?>">Шоколад 30г</a><a class="<?php echo $catalog_group === 'cacao' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('product_group', 'cacao', $shop_url)); ?>">Какао-порошок</a><a class="<?php echo $catalog_group === 'chia' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('product_group', 'chia', $shop_url)); ?>">Семена чиа</a></nav>
    <?php endif; ?>
    <?php woocommerce_content(); ?>
</div></main>
<?php get_footer();

<?php
defined('ABSPATH') || exit;

global $product;
if (!$product instanceof WC_Product) {
    $product = wc_get_product(get_the_ID());
}
if (!$product instanceof WC_Product) {
    return;
}

$preferred_related_skus = array(
    'theobroma-200-goat',
    'theobroma-200-70',
    'theobroma-30-goat',
    'theobroma-chia-100',
);
$related_ids = array_values(array_filter(array_map('wc_get_product_id_by_sku', $preferred_related_skus), static function ($product_id) use ($product): bool {
    return (int) $product_id > 0 && (int) $product_id !== $product->get_id();
}));
if (count($related_ids) < 4) {
    $fallback_ids = wc_get_related_products($product->get_id(), 4 - count($related_ids), $related_ids);
    $related_ids = array_merge($related_ids, $fallback_ids);
}
$detail_copy = $product->get_meta('_theobroma_detail_copy', true);
if (!is_array($detail_copy) || !$detail_copy) {
    $detail_copy = array_filter(array(
        wp_strip_all_tags($product->get_short_description()),
        'Натуральный шоколад с чистым, глубоким вкусом и выразительным ароматом какао-бобов.',
    ));
}
$product_details = (string) $product->get_meta('_theobroma_product_details', true);
if ($product_details === '') {
    $product_details = '<p><strong>Состав:</strong> информация указана на упаковке продукта.</p><p><strong>Условия хранения:</strong> хранить в сухом прохладном месте.</p><p><strong>Срок годности:</strong> 12 месяцев.</p>';
}
$product_benefit = (string) $product->get_meta('_theobroma_product_benefit', true);
if ($product_benefit === '') {
    $product_benefit = '<p>Кокосовый сахар придаёт шоколаду мягкую карамельную ноту и гармонично дополняет вкус какао.</p>';
}
$marketplaces = $product->get_meta('_theobroma_marketplaces', true);
if (!is_array($marketplaces)) {
    $marketplaces = array();
}
$shop_url = wc_get_page_permalink('shop');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('theobroma-product-view'); ?>>
<?php wp_body_open(); ?>
<main class="product-detail-page">
    <a class="product-detail-back" href="<?php echo esc_url($shop_url); ?>">← Назад</a>
    <a class="product-detail-close" href="<?php echo esc_url($shop_url); ?>" aria-label="Закрыть"></a>
    <section class="product-detail-hero">
        <figure class="product-detail-image"><img src="<?php echo esc_url(wp_get_attachment_image_url($product->get_image_id(), 'full') ?: wc_placeholder_img_src('full')); ?>" width="624" height="780" decoding="async" fetchpriority="high" alt="<?php echo esc_attr($product->get_name()); ?>"></figure>
        <div class="product-detail-summary">
            <h1><?php echo esc_html($product->get_name()); ?></h1>
            <div class="product-detail-price"><?php echo esc_html(number_format((float) $product->get_price(), 0, '', ' ') . ' р.'); ?></div>
            <div class="product-detail-buy"><?php woocommerce_template_single_add_to_cart(); ?><button class="product-detail-favorite" type="button" aria-label="Добавить в избранное">♡</button></div>
            <div class="product-detail-marketplaces"><a href="<?php echo esc_url($marketplaces['wb'] ?? 'https://www.wildberries.ru/'); ?>" rel="noopener">WB</a><a href="<?php echo esc_url($marketplaces['ozon'] ?? 'https://www.ozon.ru/'); ?>" rel="noopener">Ozon</a></div>
            <div class="product-detail-copy"><?php foreach ($detail_copy as $paragraph) : ?><p><?php echo esc_html($paragraph); ?></p><?php endforeach; ?></div>
        </div>
    </section>
    <section class="product-detail-accordions">
        <details open><summary>Описание продукта<i aria-hidden="true"></i></summary><div><?php echo wp_kses_post($product_details); ?></div></details>
        <details><summary>Польза кокосового сахара<i aria-hidden="true"></i></summary><div><?php echo wp_kses_post($product_benefit); ?></div></details>
    </section>
    <section class="product-related">
        <h2>Вам может понравиться</h2>
        <div class="product-related-grid">
            <?php foreach ($related_ids as $related_id) : $related = wc_get_product($related_id); if (!$related instanceof WC_Product) { continue; } ?>
                <article>
                    <a class="product-related-image" href="<?php echo esc_url(get_permalink($related_id)); ?>"><img src="<?php echo esc_url(wp_get_attachment_image_url($related->get_image_id(), 'full') ?: wc_placeholder_img_src('full')); ?>" width="312" height="390" loading="eager" decoding="async" alt="<?php echo esc_attr($related->get_name()); ?>"><span>♡</span></a>
                    <h3><a href="<?php echo esc_url(get_permalink($related_id)); ?>"><?php echo esc_html($related->get_name()); ?></a></h3>
                    <p><?php echo esc_html($related->get_short_description()); ?></p>
                    <div class="product-related-price"><?php echo esc_html(number_format((float) $related->get_price(), 0, '', ' ') . ' р.'); ?></div>
                    <a class="product-related-button ajax_add_to_cart add_to_cart_button" data-product_id="<?php echo esc_attr((string) $related_id); ?>" data-quantity="1" href="<?php echo esc_url($related->add_to_cart_url()); ?>">Добавить в корзину</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php wp_footer(); ?>
</body>
</html>

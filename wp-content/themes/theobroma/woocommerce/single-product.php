<?php
defined('ABSPATH') || exit;

global $product;
if (!$product instanceof WC_Product) {
    $product = wc_get_product(get_the_ID());
}
if (!$product instanceof WC_Product) {
    return;
}

$related_ids = theobroma_related_product_ids($product);
$mobile_related_ids = $related_ids;
$tablet_related_ids = $related_ids;
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
$product_benefits = $product->get_meta('_theobroma_product_benefits', true);
if (!is_array($product_benefits)) {
    $legacy_benefit = (string) $product->get_meta('_theobroma_product_benefit', true);
    $product_benefits = $legacy_benefit !== '' ? array(array(
        'title' => theobroma_product_benefit_title($product),
        'content' => $legacy_benefit,
    )) : array();
}
$product_benefits = array_values(array_filter(array_map(static function ($benefit): array {
    return is_array($benefit) ? array(
        'title' => trim((string) ($benefit['title'] ?? '')),
        'content' => (string) ($benefit['content'] ?? ''),
    ) : array('title' => '', 'content' => '');
}, $product_benefits), static fn(array $benefit): bool => $benefit['title'] !== '' && $benefit['content'] !== ''));
$marketplaces = $product->get_meta('_theobroma_marketplaces', true);
if (!is_array($marketplaces)) {
    $marketplaces = array();
}
$product_image_ids = array_values(array_unique(array_filter(array_merge(
    array($product->get_image_id()),
    $product->get_gallery_image_ids()
))));
if (!$product_image_ids) {
    $product_image_ids = array(0);
}
$detail_image_id = absint($product->get_meta('_theobroma_product_detail_image_id', true));
$main_image_url = $detail_image_id ? wp_get_attachment_image_url($detail_image_id, 'full') : '';
if (!$main_image_url) {
    $bundled_detail_image = '/assets/images/products/detail/' . sanitize_file_name($product->get_sku()) . '.webp';
    if (is_file(get_template_directory() . $bundled_detail_image)) {
        $main_image_url = get_template_directory_uri() . $bundled_detail_image;
    }
}
if (!$main_image_url) {
    $main_image_url = $product_image_ids[0] ? wp_get_attachment_image_url($product_image_ids[0], 'full') : wc_placeholder_img_src('full');
}
$shop_url = wc_get_page_permalink('shop');
get_header();
?>
<div class="product-modal-underlay" aria-hidden="true"><i></i></div>
<div class="product-modal-source" hidden>
<main class="product-detail-page product-detail-type-<?php echo esc_attr(sanitize_html_class($product->get_type())); ?> product-detail-sku-<?php echo esc_attr(sanitize_html_class($product->get_sku())); ?> product-detail-benefits-<?php echo esc_attr((string) count($product_benefits)); ?>">
    <a class="product-detail-back" href="<?php echo esc_url($shop_url); ?>">← Назад</a>
    <a class="product-detail-close" href="<?php echo esc_url($shop_url); ?>" aria-label="Закрыть"></a>
    <section class="product-detail-hero">
        <div class="product-detail-gallery">
            <figure class="product-detail-image">
                <button class="product-detail-zoom-trigger" type="button" data-product-image-zoom aria-label="<?php echo esc_attr(sprintf('Увеличить изображение товара «%s»', $product->get_name())); ?>">
                    <img data-product-main-image src="<?php echo esc_url($main_image_url ?: wc_placeholder_img_src('full')); ?>" width="624" height="780" decoding="async" fetchpriority="high" alt="<?php echo esc_attr($product->get_name()); ?>">
                </button>
            </figure>
            <?php if (count($product_image_ids) > 1) : ?>
                <div class="product-detail-thumbnails" aria-label="Галерея товара">
                    <?php foreach (array_slice($product_image_ids, 0, 9) as $image_index => $image_id) : ?>
                        <?php $full_image_url = $image_index === 0 ? $main_image_url : ($image_id ? wp_get_attachment_image_url($image_id, 'full') : wc_placeholder_img_src('full')); ?>
                        <button class="<?php echo $image_index === 0 ? 'is-active' : ''; ?>" type="button" data-product-gallery-image="<?php echo esc_url($full_image_url ?: wc_placeholder_img_src('full')); ?>" aria-label="Показать изображение <?php echo esc_attr((string) ($image_index + 1)); ?>">
                            <?php echo wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, array('loading' => 'eager', 'alt' => '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="product-detail-summary">
            <h1><?php echo esc_html(theobroma_product_modal_title($product)); ?></h1>
            <div class="product-detail-price"><?php echo esc_html(number_format((float) $product->get_price(), 0, '', ' ') . ' р.'); ?></div>
            <div class="product-detail-buy"><?php woocommerce_template_single_add_to_cart(); ?><button class="product-detail-favorite" type="button" data-wishlist-toggle data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>" aria-label="Добавить в избранное" aria-pressed="false">♡</button></div>
            <div class="product-detail-marketplaces"><a href="<?php echo esc_url($marketplaces['wb'] ?? 'https://www.wildberries.ru/'); ?>" rel="noopener">WB</a><a href="<?php echo esc_url($marketplaces['ozon'] ?? 'https://www.ozon.ru/'); ?>" rel="noopener">Ozon</a></div>
            <div class="product-detail-copy"><?php foreach ($detail_copy as $paragraph) : ?><p><?php echo nl2br(esc_html($paragraph)); ?></p><?php endforeach; ?></div>
        </div>
    </section>
    <section class="product-detail-accordions">
        <details open><summary>Описание продукта<i aria-hidden="true"></i></summary><div><?php echo wp_kses_post($product_details); ?></div></details>
        <?php foreach ($product_benefits as $product_benefit) : ?>
            <details><summary><?php echo esc_html($product_benefit['title']); ?><i aria-hidden="true"></i></summary><div><?php echo wp_kses_post($product_benefit['content']); ?></div></details>
        <?php endforeach; ?>
    </section>
    <section class="product-related">
        <h2>Вам может понравиться</h2>
        <?php foreach (array('desktop' => $related_ids, 'tablet' => $tablet_related_ids, 'mobile' => $mobile_related_ids) as $related_layout => $layout_related_ids) : ?>
        <div class="product-related-grid product-related-grid-<?php echo esc_attr($related_layout); ?>">
            <?php foreach ($layout_related_ids as $related_id) : $related = wc_get_product($related_id); if (!$related instanceof WC_Product) { continue; } ?>
                <article>
                    <a class="product-related-image" href="<?php echo esc_url(get_permalink($related_id)); ?>"><img src="<?php echo esc_url(wp_get_attachment_image_url($related->get_image_id(), 'full') ?: wc_placeholder_img_src('full')); ?>" width="312" height="390" loading="eager" decoding="async" alt="<?php echo esc_attr($related->get_name()); ?>"><span>♡</span></a>
                    <h3><a href="<?php echo esc_url(get_permalink($related_id)); ?>"><?php echo esc_html($related->get_name()); ?></a></h3>
                    <p><?php echo esc_html($related->get_short_description()); ?></p>
                    <div class="product-related-price"><?php echo esc_html(number_format((float) $related->get_price(), 0, '', ' ') . ' р.'); ?></div>
                    <a
                        class="product-related-button product_type_<?php echo esc_attr($related->get_type()); ?> add_to_cart_button ajax_add_to_cart"
                        href="<?php echo esc_url($related->add_to_cart_url()); ?>"
                        data-quantity="1"
                        data-product_id="<?php echo esc_attr((string) $related_id); ?>"
                        data-product_sku="<?php echo esc_attr($related->get_sku()); ?>"
                        aria-label="<?php echo esc_attr(sprintf('Добавить «%s» в корзину', $related->get_name())); ?>"
                        rel="nofollow"
                    >Добавить в корзину</a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </section>
</main>
</div>
<?php get_footer(); ?>

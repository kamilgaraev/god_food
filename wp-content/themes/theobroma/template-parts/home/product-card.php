<?php
/**
 * Homepage product card.
 *
 * @var array $args Template arguments passed by get_template_part().
 */

defined('ABSPATH') || exit;

$product = $args['product'] ?? null;
if (!$product instanceof WC_Product) {
    return;
}

$title = preg_replace('/\s+г$/u', 'г', $product->get_name());
$title_main = preg_replace('/г$/u', '', (string) $title);
$description = str_replace('зелёной', 'зеленой', wp_strip_all_tags($product->get_short_description()));
$description = preg_replace('/\b(На|С|с|и|в) /u', '$1 ', $description);
$price = wc_format_localized_price($product->get_price());
?>
<article class="product">
    <a href="<?php echo esc_url($product->get_permalink()); ?>">
        <div class="product-image" role="img" aria-label="<?php echo esc_attr($title); ?>"></div>
        <h3><?php echo esc_html((string) $title_main); ?><span class="product-unit">г</span></h3>
        <p><?php echo esc_html((string) $description); ?></p>
        <span class="price"><?php echo esc_html($price); ?>р.</span>
    </a>
</article>

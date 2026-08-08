<?php
/**
 * Editorial homepage product card.
 *
 * @var array $args Template arguments passed by get_template_part().
 */

defined('ABSPATH') || exit;

$product = $args['product'] ?? null;
if (!$product instanceof WC_Product) {
    return;
}

$is_in_cart = false;
if (function_exists('WC') && WC()->cart) {
    foreach (WC()->cart->get_cart() as $cart_item) {
        if ((int) ($cart_item['product_id'] ?? 0) === $product->get_id()) {
            $is_in_cart = true;
            break;
        }
    }
}
$can_add = $product->is_purchasable() && $product->is_in_stock() && $product->supports('ajax_add_to_cart');
$button_classes = array('home-product-card__button');
if ($can_add) {
    $button_classes = array_merge($button_classes, array('product_type_' . $product->get_type(), 'add_to_cart_button', 'ajax_add_to_cart'));
}
if ($is_in_cart) {
    $button_classes[] = 'is-in-cart';
}
?>
<article class="home-product-card" data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>">
    <a class="home-product-card__image" href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link aria-label="<?php echo esc_attr($product->get_name()); ?>">
        <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', array('loading' => 'lazy', 'decoding' => 'async', 'fetchpriority' => 'low'))); ?>
        <?php if (!empty($args['bestseller'])) : ?><span class="home-product-card__badge">Бестселлер</span><?php endif; ?>
    </a>
    <div class="home-product-card__heading">
        <h3><a href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link><?php echo esc_html($product->get_name()); ?></a></h3>
        <span class="home-product-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
    </div>
    <p><?php echo esc_html(wp_strip_all_tags($product->get_short_description())); ?></p>
    <a
        class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
        href="<?php echo esc_url($can_add ? $product->add_to_cart_url() : $product->get_permalink()); ?>"
        <?php if ($can_add) : ?>data-quantity="1" data-product_id="<?php echo esc_attr((string) $product->get_id()); ?>" data-product_sku="<?php echo esc_attr($product->get_sku()); ?>" rel="nofollow"<?php endif; ?>
        aria-label="<?php echo esc_attr($can_add ? $product->add_to_cart_description() : 'Открыть карточку товара'); ?>"
    ><?php echo esc_html($is_in_cart ? 'В корзине' : ($can_add ? 'В корзину' : 'Подробнее')); ?></a>
</article>

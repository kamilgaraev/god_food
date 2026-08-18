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

$wrapper_tag = ($args['wrapper_tag'] ?? 'article') === 'li' ? 'li' : 'article';
$wrapper_classes = array_merge(array('home-product-card'), (array) ($args['wrapper_classes'] ?? array()));
$wrapper_classes = array_values(array_unique(array_filter(array_map('sanitize_html_class', $wrapper_classes))));

$loop_hook_callbacks = array(
    'woocommerce_before_shop_loop_item' => array('woocommerce_template_loop_product_link_open'),
    'woocommerce_before_shop_loop_item_title' => array(
        'woocommerce_show_product_loop_sale_flash',
        'woocommerce_template_loop_product_thumbnail',
        'theobroma_catalog_thumbnail_frame_open',
        'theobroma_catalog_thumbnail_frame_close',
    ),
    'woocommerce_shop_loop_item_title' => array('woocommerce_template_loop_product_title'),
    'woocommerce_after_shop_loop_item_title' => array(
        'woocommerce_template_loop_rating',
        'theobroma_catalog_excerpt',
        'woocommerce_template_loop_price',
    ),
    'woocommerce_after_shop_loop_item' => array(
        'woocommerce_template_loop_product_link_close',
        'woocommerce_template_loop_add_to_cart',
    ),
);
$run_woocommerce_loop_hook = static function (string $hook) use ($args, $loop_hook_callbacks): void {
    if (empty($args['woocommerce_loop_hooks'])) {
        return;
    }

    $removed_callbacks = array();
    foreach ($loop_hook_callbacks[$hook] ?? array() as $callback) {
        $priority = has_action($hook, $callback);
        if ($priority !== false) {
            remove_action($hook, $callback, $priority);
            $removed_callbacks[] = array($callback, $priority);
        }
    }

    do_action($hook);

    foreach ($removed_callbacks as [$callback, $priority]) {
        add_action($hook, $callback, $priority);
    }
};

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
$button_classes = array('home-product-card__button', 'product_type_' . $product->get_type());
if ($can_add) {
    $button_classes = array_merge($button_classes, array('add_to_cart_button', 'ajax_add_to_cart'));
}
if ($is_in_cart) {
    $button_classes[] = 'is-in-cart';
}
$button_text = $is_in_cart ? 'В корзине' : ($can_add ? 'В корзину' : 'Подробнее');
$button_args = apply_filters('woocommerce_loop_add_to_cart_args', array(
    'quantity' => 1,
    'class' => implode(' ', $button_classes),
    'attributes' => array(
        'data-product_id' => (string) $product->get_id(),
        'data-product_sku' => $product->get_sku(),
        'aria-label' => $can_add ? $product->add_to_cart_description() : 'Открыть карточку товара',
        'rel' => 'nofollow',
    ),
), $product);
$button_html = sprintf(
    '<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
    esc_url($can_add ? $product->add_to_cart_url() : $product->get_permalink()),
    esc_attr((string) ($button_args['quantity'] ?? 1)),
    esc_attr((string) ($button_args['class'] ?? implode(' ', $button_classes))),
    isset($button_args['attributes']) ? wc_implode_html_attributes($button_args['attributes']) : '',
    esc_html($button_text)
);
$button_html = apply_filters('woocommerce_loop_add_to_cart_link', $button_html, $product, $button_args);
?>
<<?php echo $wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constrained to article or li above. ?> class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>">
    <?php $run_woocommerce_loop_hook('woocommerce_before_shop_loop_item'); ?>
    <?php $run_woocommerce_loop_hook('woocommerce_before_shop_loop_item_title'); ?>
    <a class="home-product-card__image" href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link aria-label="<?php echo esc_attr($product->get_name()); ?>">
        <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', array('loading' => 'lazy', 'decoding' => 'async', 'fetchpriority' => 'low'))); ?>
        <?php if (!empty($args['bestseller'])) : ?><span class="home-product-card__badge">Бестселлер</span><?php endif; ?>
    </a>
    <?php $run_woocommerce_loop_hook('woocommerce_shop_loop_item_title'); ?>
    <div class="home-product-card__heading">
        <h3><a href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link><?php echo esc_html($product->get_name()); ?></a></h3>
        <span class="home-product-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
    </div>
    <?php $run_woocommerce_loop_hook('woocommerce_after_shop_loop_item_title'); ?>
    <p><?php echo esc_html(wp_strip_all_tags($product->get_short_description())); ?></p>
    <?php echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated safely above; WooCommerce filters are trusted extension points. ?>
    <?php $run_woocommerce_loop_hook('woocommerce_after_shop_loop_item'); ?>
</<?php echo $wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constrained to article or li above. ?>>

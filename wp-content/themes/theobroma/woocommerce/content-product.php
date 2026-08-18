<?php
/**
 * Product card used by WooCommerce loops.
 */

defined('ABSPATH') || exit;

global $product;

if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}

get_template_part('template-parts/home/product-card', null, array(
    'product' => $product,
    'wrapper_tag' => 'li',
    'wrapper_classes' => function_exists('wc_get_product_class')
        ? wc_get_product_class(array(), $product)
        : array('product'),
    'woocommerce_loop_hooks' => true,
));

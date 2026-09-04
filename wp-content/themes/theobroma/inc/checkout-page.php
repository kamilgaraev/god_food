<?php
/** Use the same single-address checkout on the direct checkout URL and in the cart. */
declare(strict_types=1);

function theobroma_classic_checkout_page(string $content): string
{
    if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url()
        || !is_main_query() || !in_the_loop() || !has_block('woocommerce/checkout', $content)) {
        return $content;
    }

    return '<div class="commerce-cart-checkout"><h3 id="commerce-checkout-title">Получатель</h3>[woocommerce_checkout]</div>';
}
add_filter('the_content', 'theobroma_classic_checkout_page', 7);

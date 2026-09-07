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

/** Enhance both direct checkout and the AJAX cart with the same step navigation. */
function theobroma_checkout_step_assets(): void
{
    if (!class_exists('WooCommerce')) {
        return;
    }
    $path = get_template_directory();
    $url = get_template_directory_uri();
    wp_enqueue_style('theobroma-checkout-steps', $url . '/assets/css/checkout-steps.css', array('theobroma-style'), (string) filemtime($path . '/assets/css/checkout-steps.css'));
    wp_enqueue_script('theobroma-checkout-steps', $url . '/assets/js/checkout-steps.js', array('jquery', 'wc-checkout'), (string) filemtime($path . '/assets/js/checkout-steps.js'), array('strategy' => 'defer', 'in_footer' => true));
}
add_action('wp_enqueue_scripts', 'theobroma_checkout_step_assets', 30);

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

/** Show the promo code only while the store has published coupons. */
function theobroma_checkout_has_coupons(): bool
{
    if (!function_exists('wc_coupons_enabled') || !wc_coupons_enabled()) {
        return false;
    }

    return (bool) get_posts(array(
        'post_type' => 'shop_coupon',
        'post_status' => 'publish',
        'fields' => 'ids',
        'numberposts' => 1,
    ));
}

function theobroma_checkout_coupon_list(): string
{
    if (!function_exists('WC') || !WC()->cart) {
        return '';
    }

    ob_start();
    foreach (WC()->cart->get_applied_coupons() as $code) {
        if ($code === 'theobroma-bonus') {
            continue;
        }
        $discount = (float) WC()->cart->get_coupon_discount_amount($code, false)
            + (float) WC()->cart->get_coupon_discount_tax_amount($code);
        ?>
        <div class="commerce-coupon__applied">
            <span>Промокод <strong><?php echo esc_html($code); ?></strong></span>
            <span class="commerce-coupon__discount">−<?php echo wp_kses_post(wc_price($discount)); ?></span>
            <button type="button" data-coupon-remove="<?php echo esc_attr($code); ?>" aria-label="Удалить промокод <?php echo esc_attr($code); ?>">Удалить</button>
        </div>
        <?php
    }

    return (string) ob_get_clean();
}

/** This hook runs between the order table and payment fragment, inside checkout. */
function theobroma_render_checkout_coupon(): void
{
    if (!theobroma_checkout_has_coupons() || !function_exists('WC') || !WC()->cart) {
        return;
    }

    $input_id = wp_unique_id('theobroma-coupon-');
    ?>
    <section class="commerce-coupon" aria-label="Промокод"
        data-coupon-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-coupon-nonce="<?php echo esc_attr(wp_create_nonce('theobroma_checkout_coupon')); ?>">
        <label class="commerce-coupon__label" for="<?php echo esc_attr($input_id); ?>">Промокод</label>
        <div class="commerce-coupon__controls">
            <input id="<?php echo esc_attr($input_id); ?>" type="text" data-coupon-input
                autocomplete="off" placeholder="Введите промокод">
            <button type="button" data-coupon-apply>Применить</button>
        </div>
        <p class="commerce-coupon__status" data-coupon-status role="status" aria-live="polite"></p>
        <div data-coupon-list><?php echo theobroma_checkout_coupon_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    </section>
    <?php
}
add_action('woocommerce_checkout_order_review', 'theobroma_render_checkout_coupon', 15);

function theobroma_ajax_checkout_coupon(): void
{
    if (!check_ajax_referer('theobroma_checkout_coupon', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Обновите страницу и попробуйте ещё раз.'), 403);
    }
    if (!theobroma_checkout_has_coupons() || !function_exists('WC')) {
        wp_send_json_error(array('message' => 'Промокоды сейчас недоступны.'), 400);
    }
    if (!WC()->cart) {
        wc_load_cart();
    }
    if (!WC()->cart) {
        wp_send_json_error(array('message' => 'Корзина недоступна.'), 400);
    }

    $raw_mode = $_POST['mode'] ?? 'apply';
    $raw_code = $_POST['code'] ?? '';
    $mode = is_string($raw_mode) ? sanitize_key(wp_unslash($raw_mode)) : '';
    $code = is_string($raw_code) ? wc_format_coupon_code(sanitize_text_field(wp_unslash($raw_code))) : '';
    if ($code === '' || $code === 'theobroma-bonus') {
        wp_send_json_error(array('message' => 'Введите действующий промокод.'), 400);
    }

    if ($mode === 'remove') {
        if (!WC()->cart->has_discount($code)) {
            wp_send_json_error(array('message' => 'Этот промокод уже удалён.'), 400);
        }
        WC()->cart->remove_coupon($code);
        $message = 'Промокод удалён.';
    } elseif ($mode === 'apply') {
        if (WC()->cart->has_discount($code)) {
            wp_send_json_error(array('message' => 'Этот промокод уже применён.'), 400);
        }
        $previous_notices = wc_get_notices();
        $applied = WC()->cart->apply_coupon($code);
        $errors = wc_get_notices('error');
        wc_set_notices($previous_notices);
        if (!$applied) {
            $error = end($errors);
            $message = is_array($error) ? wp_strip_all_tags((string) ($error['notice'] ?? '')) : '';
            wp_send_json_error(array('message' => $message ?: 'Промокод не подошёл. Проверьте условия его применения.'), 400);
        }
        $message = 'Промокод применён.';
    } else {
        wp_send_json_error(array('message' => 'Неизвестное действие.'), 400);
    }

    WC()->cart->calculate_totals();
    wp_send_json_success(array(
        'message' => $message,
        'applied_html' => theobroma_checkout_coupon_list(),
    ));
}
add_action('wp_ajax_theobroma_checkout_coupon', 'theobroma_ajax_checkout_coupon');
add_action('wp_ajax_nopriv_theobroma_checkout_coupon', 'theobroma_ajax_checkout_coupon');

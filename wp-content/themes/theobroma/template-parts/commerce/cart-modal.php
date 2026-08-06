<?php
defined('ABSPATH') || exit;

if (!function_exists('WC') || !WC()->cart) {
    return;
}

$cart = WC()->cart->get_cart();
?>
<div class="commerce-cart<?php echo $cart ? '' : ' commerce-cart--empty'; ?>" data-commerce-cart>
    <?php if ($cart) : ?>
        <header class="commerce-cart-header">
            <h2>Ваш заказ</h2>
            <button type="button" class="commerce-cart-clear" data-cart-clear>Очистить корзину</button>
        </header>
    <?php endif; ?>

    <?php if (!$cart) : ?>
        <div class="commerce-cart-empty">
            <button type="button" class="commerce-cart-empty-close" data-commerce-close aria-label="Закрыть"></button>
            <p>Пожалуйста, добавьте товары в корзину</p>
        </div>
    <?php else : ?>
        <div class="commerce-cart-products">
            <?php foreach ($cart as $cart_key => $cart_item) : ?>
                <?php
                $cart_product = $cart_item['data'] ?? null;
                if (!$cart_product instanceof WC_Product || !$cart_product->exists()) {
                    continue;
                }
                $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
                ?>
                <article class="commerce-cart-product" data-cart-key="<?php echo esc_attr($cart_key); ?>">
                    <a class="commerce-cart-thumb" href="<?php echo esc_url($cart_product->get_permalink()); ?>" data-product-modal-link>
                        <?php echo wp_kses_post($cart_product->get_image('woocommerce_thumbnail', array('loading' => 'eager'))); ?>
                    </a>
                    <h3><a href="<?php echo esc_url($cart_product->get_permalink()); ?>" data-product-modal-link><?php echo esc_html(theobroma_frontend_product_title($cart_product->get_name(), $cart_product->get_id())); ?></a></h3>
                    <div class="commerce-cart-quantity" aria-label="Количество">
                        <button type="button" data-cart-quantity="<?php echo esc_attr((string) max(0, $quantity - 1)); ?>" aria-label="Уменьшить количество">−</button>
                        <span><?php echo esc_html((string) $quantity); ?></span>
                        <button type="button" data-cart-quantity="<?php echo esc_attr((string) ($quantity + 1)); ?>" aria-label="Увеличить количество">+</button>
                    </div>
                    <div class="commerce-cart-price"><?php echo wp_kses_post(WC()->cart->get_product_subtotal($cart_product, $quantity)); ?></div>
                    <button type="button" class="commerce-cart-remove" data-cart-quantity="0" aria-label="Удалить товар"></button>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="commerce-cart-subtotal">
            <span>Сумма:</span>
            <strong><?php echo wp_kses_post(WC()->cart->get_cart_subtotal()); ?></strong>
        </div>

        <?php if (!is_user_logged_in()) : ?>
            <p class="commerce-cart-auth">Покупали ранее? <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Авторизуйтесь или зарегистрируйтесь</a></p>
        <?php endif; ?>

        <div class="commerce-cart-notes">
            <p><strong>Обработка заказов</strong><br>Принятие и обработка заказов осуществляется с понедельника по пятницу с 9:00 до 18:00.</p>
            <p><strong>Важное о доставке</strong><br>Мы отправляем заказы из Москвы по всей территории России, а также в Республики Беларусь и Казахстан, службой курьерской доставки CDEK.<br>Сроки доставки устанавливаются согласно условиям транспортной компании, курьерской службы.<br>После оформления заказа мы пришлём вам трек номер для отслеживания отправления.</p>
            <a href="<?php echo esc_url(theobroma_page_url('Доставка и оплата')); ?>">Подробная информация о доставке и оплате</a>
        </div>

        <section class="commerce-cart-checkout" aria-labelledby="commerce-checkout-title">
            <h3 id="commerce-checkout-title">Доставка</h3>
            <?php echo do_shortcode('[woocommerce_checkout]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
    <?php endif; ?>
</div>

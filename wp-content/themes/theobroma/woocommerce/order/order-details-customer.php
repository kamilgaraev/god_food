<?php
/**
 * Order Customer Details.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

defined('ABSPATH') || exit;

$shipping_address = trim((string) $order->get_formatted_shipping_address(''));
$show_shipping = !wc_ship_to_billing_address_only()
    && $order->needs_shipping_address()
    && $shipping_address !== '';
?>
<section class="woocommerce-customer-details">
    <h2><?php esc_html_e('Данные получателя', 'theobroma'); ?></h2>

    <section class="woocommerce-columns woocommerce-columns--<?php echo $show_shipping ? '2' : '1'; ?> woocommerce-columns--addresses col2-set addresses">
        <div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">
            <h3 class="woocommerce-column__title"><?php esc_html_e('Платёжный адрес', 'theobroma'); ?></h3>
            <address>
                <?php echo wp_kses_post($order->get_formatted_billing_address(esc_html__('Н/Д', 'theobroma'))); ?>

                <?php if ($order->get_billing_phone()) : ?>
                    <p class="woocommerce-customer-details--phone"><?php echo esc_html($order->get_billing_phone()); ?></p>
                <?php endif; ?>
                <?php if ($order->get_billing_email()) : ?>
                    <p class="woocommerce-customer-details--email"><?php echo esc_html($order->get_billing_email()); ?></p>
                <?php endif; ?>
                <?php do_action('woocommerce_order_details_after_customer_address', 'billing', $order); ?>
            </address>
        </div>

        <?php if ($show_shipping) : ?>
            <div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
                <h3 class="woocommerce-column__title"><?php esc_html_e('Адрес доставки', 'theobroma'); ?></h3>
                <address>
                    <?php echo wp_kses_post($shipping_address); ?>
                    <?php if ($order->get_shipping_phone()) : ?>
                        <p class="woocommerce-customer-details--phone"><?php echo esc_html($order->get_shipping_phone()); ?></p>
                    <?php endif; ?>
                    <?php do_action('woocommerce_order_details_after_customer_address', 'shipping', $order); ?>
                </address>
            </div>
        <?php endif; ?>
    </section>

    <?php do_action('woocommerce_order_details_after_customer_details', $order); ?>
</section>

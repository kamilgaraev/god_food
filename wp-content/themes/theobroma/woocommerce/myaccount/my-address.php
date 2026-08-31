<?php
/**
 * Customer delivery address.
 *
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined('ABSPATH') || exit;

$address = wc_get_account_formatted_address('billing');
$edit_url = wc_get_endpoint_url('edit-address', 'billing');
?>

<section class="theobroma-address-book">
    <p class="theobroma-address-book__lead">Сохраните адрес, куда нужно доставлять ваши заказы. Его можно изменить в любое время.</p>

    <article class="theobroma-address-card">
        <header>
            <div>
                <small>Основной адрес</small>
                <h2>Адрес доставки</h2>
            </div>
            <a class="button" href="<?php echo esc_url($edit_url); ?>">
                <?php echo $address ? 'Изменить адрес' : 'Добавить адрес доставки'; ?>
            </a>
        </header>

        <?php if ($address) : ?>
            <address><?php echo wp_kses_post($address); ?></address>
        <?php else : ?>
            <p class="theobroma-address-card__empty">Адрес пока не указан. Добавьте его, чтобы быстрее оформлять следующие заказы.</p>
        <?php endif; ?>

        <?php do_action('woocommerce_my_account_after_my_address', 'billing'); ?>
    </article>
</section>

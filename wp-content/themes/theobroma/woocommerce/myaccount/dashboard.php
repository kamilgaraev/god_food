<?php
defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$display_name = $current_user->display_name ?: $current_user->user_login;
?>
<section class="theobroma-account-dashboard">
    <h1>Здравствуйте, <?php echo esc_html($display_name); ?></h1>
    <p class="account-lead">Здесь можно посмотреть заказы и бонусы, сохранить адрес доставки и изменить данные профиля.</p>
    <div class="account-dashboard-grid">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><span>Заказы</span><small>История и статусы покупок</small></a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('bonuses')); ?>"><span>Бонусы</span><small>Баланс и история операций</small></a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><span>Адрес доставки</span><small>Адрес для получения заказов</small></a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><span>Профиль</span><small>Имя, почта и пароль</small></a>
    </div>
    <?php do_action('woocommerce_account_dashboard'); ?>
</section>

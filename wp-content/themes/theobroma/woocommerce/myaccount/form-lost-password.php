<?php
/**
 * Lost password form.
 *
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_lost_password_form');
?>

<section class="account-recovery-card">
    <h2><?php esc_html_e('Сброс пароля', 'theobroma'); ?></h2>
    <p class="account-recovery-card__intro">
        <?php
        echo apply_filters(
            'woocommerce_lost_password_message',
            esc_html__('Укажите Email или имя пользователя. Мы пришлём ссылку для создания нового пароля.', 'theobroma')
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </p>

    <form method="post" class="woocommerce-ResetPassword lost_reset_password">
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="user_login"><?php esc_html_e('Email или имя пользователя', 'theobroma'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Обязательное поле', 'theobroma'); ?></span></label>
            <input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" required aria-required="true">
        </p>

        <?php do_action('woocommerce_lostpassword_form'); ?>

        <p class="woocommerce-form-row form-row account-recovery-card__submit">
            <input type="hidden" name="wc_reset_password" value="true">
            <button type="submit" class="woocommerce-Button button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" value="<?php esc_attr_e('Получить ссылку', 'theobroma'); ?>"><?php esc_html_e('Получить ссылку', 'theobroma'); ?></button>
        </p>

        <?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
    </form>

    <p class="account-recovery-card__back"><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php esc_html_e('Вернуться ко входу', 'theobroma'); ?></a></p>
</section>

<?php do_action('woocommerce_after_lost_password_form'); ?>

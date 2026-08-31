<?php
/**
 * Reset password form.
 *
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_reset_password_form');
?>

<section class="account-recovery-card">
    <h2><?php esc_html_e('Новый пароль', 'theobroma'); ?></h2>
    <p class="account-recovery-card__intro">
        <?php
        echo apply_filters(
            'woocommerce_reset_password_message',
            esc_html__('Придумайте новый пароль и повторите его ещё раз.', 'theobroma')
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </p>

    <form method="post" class="woocommerce-ResetPassword lost_reset_password">
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_1"><?php esc_html_e('Новый пароль', 'theobroma'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Обязательное поле', 'theobroma'); ?></span></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_1" id="password_1" autocomplete="new-password" required aria-required="true">
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_2"><?php esc_html_e('Повторите пароль', 'theobroma'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Обязательное поле', 'theobroma'); ?></span></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_2" id="password_2" autocomplete="new-password" required aria-required="true">
        </p>

        <input type="hidden" name="reset_key" value="<?php echo esc_attr($args['key']); ?>">
        <input type="hidden" name="reset_login" value="<?php echo esc_attr($args['login']); ?>">

        <?php do_action('woocommerce_resetpassword_form'); ?>

        <p class="woocommerce-form-row form-row account-recovery-card__submit">
            <input type="hidden" name="wc_reset_password" value="true">
            <button type="submit" class="woocommerce-Button button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" value="<?php esc_attr_e('Сохранить пароль', 'theobroma'); ?>"><?php esc_html_e('Сохранить пароль', 'theobroma'); ?></button>
        </p>

        <?php wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' ); ?>
    </form>

    <p class="account-recovery-card__back"><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php esc_html_e('Вернуться ко входу', 'theobroma'); ?></a></p>
</section>

<?php do_action('woocommerce_after_reset_password_form'); ?>

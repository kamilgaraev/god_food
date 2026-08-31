<?php
/**
 * Customer login and registration forms.
 *
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

defined('ABSPATH') || exit;

$registration_enabled = 'yes' === get_option('woocommerce_enable_myaccount_registration');
$show_registration = $registration_enabled && isset($_POST['register']); // phpcs:ignore WordPress.Security.NonceVerification.Missing

do_action('woocommerce_before_customer_login_form');
?>

<section class="account-page-auth" data-account-page-auth>
    <div class="account-page-auth__view" id="account-page-login" data-account-page-view="login"<?php echo $show_registration ? ' hidden' : ''; ?>>
        <p class="account-page-auth__eyebrow"><?php esc_html_e('С возвращением', 'theobroma'); ?></p>
        <h2><?php esc_html_e('Вход', 'woocommerce'); ?></h2>
        <p class="account-page-auth__intro"><?php esc_html_e('Войдите, чтобы посмотреть заказы и данные профиля.', 'theobroma'); ?></p>

        <form class="woocommerce-form woocommerce-form-login login" method="post">
            <?php do_action('woocommerce_login_form_start'); ?>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="username"><?php esc_html_e('Имя пользователя или Email', 'theobroma'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo !empty($_POST['username']) ? esc_attr(wp_unslash($_POST['username'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>" required aria-required="true">
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password"><?php esc_html_e('Пароль', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true">
            </p>

            <?php do_action('woocommerce_login_form'); ?>

            <div class="account-page-auth__options">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever">
                    <span><?php esc_html_e('Запомнить меня', 'woocommerce'); ?></span>
                </label>
                <a href="<?php echo esc_url(wc_lostpassword_url()); ?>"><?php esc_html_e('Забыли пароль?', 'woocommerce'); ?></a>
            </div>

            <p class="form-row account-page-auth__submit">
                <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e('Войти', 'woocommerce'); ?>"><?php esc_html_e('Войти', 'woocommerce'); ?></button>
            </p>

            <?php do_action('woocommerce_login_form_end'); ?>
        </form>

        <?php if ($registration_enabled) : ?>
            <p class="account-page-auth__switch">
                <?php esc_html_e('Нет аккаунта?', 'theobroma'); ?>
                <button type="button" data-account-page-show="register" aria-controls="account-page-register" aria-expanded="false"><?php esc_html_e('Зарегистрироваться', 'theobroma'); ?></button>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($registration_enabled) : ?>
        <div class="account-page-auth__view" id="account-page-register" data-account-page-view="register"<?php echo $show_registration ? '' : ' hidden'; ?>>
            <p class="account-page-auth__eyebrow"><?php esc_html_e('Новый профиль', 'theobroma'); ?></p>
            <h2><?php esc_html_e('Регистрация', 'woocommerce'); ?></h2>
            <p class="account-page-auth__intro"><?php esc_html_e('Создайте профиль, чтобы быстрее оформлять заказы.', 'theobroma'); ?></p>

            <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action('woocommerce_register_form_tag'); ?>>
                <?php do_action('woocommerce_register_form_start'); ?>

                <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="reg_username"><?php esc_html_e('Имя пользователя', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo !empty($_POST['username']) ? esc_attr(wp_unslash($_POST['username'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>" required aria-required="true">
                    </p>
                <?php endif; ?>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_email"><?php esc_html_e('Email', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                    <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo !empty($_POST['email']) ? esc_attr(wp_unslash($_POST['email'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>" required aria-required="true">
                </p>

                <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="reg_password"><?php esc_html_e('Пароль', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true">
                    </p>
                <?php else : ?>
                    <p><?php esc_html_e('Ссылка для установки пароля придёт на вашу почту.', 'theobroma'); ?></p>
                <?php endif; ?>

                <?php do_action('woocommerce_register_form'); ?>

                <div class="woocommerce-privacy-policy-text account-page-auth__privacy">
                    <?php wc_get_privacy_policy_text('registration'); ?>
                </div>

                <p class="woocommerce-form-row form-row account-page-auth__submit">
                    <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                    <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e('Зарегистрироваться', 'theobroma'); ?>"><?php esc_html_e('Зарегистрироваться', 'theobroma'); ?></button>
                </p>

                <?php do_action('woocommerce_register_form_end'); ?>
            </form>

            <p class="account-page-auth__switch">
                <?php esc_html_e('Уже есть аккаунт?', 'theobroma'); ?>
                <button type="button" data-account-page-show="login" aria-controls="account-page-login" aria-expanded="false"><?php esc_html_e('Войти', 'woocommerce'); ?></button>
            </p>
        </div>
    <?php endif; ?>
</section>

<?php do_action('woocommerce_after_customer_login_form'); ?>

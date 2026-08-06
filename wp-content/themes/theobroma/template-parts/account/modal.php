<?php
defined('ABSPATH') || exit;

$account_url = wc_get_page_permalink('myaccount');
$posted_login = isset($_POST['username']) && is_string($_POST['username'])
    ? sanitize_email(wp_unslash($_POST['username']))
    : '';
$posted_email = isset($_POST['email']) && is_string($_POST['email'])
    ? sanitize_email(wp_unslash($_POST['email']))
    : '';
?>
<div class="account-modal" id="account-modal" hidden aria-hidden="true">
    <div class="account-modal-backdrop" data-account-close></div>
    <section class="account-modal-panel" role="dialog" aria-modal="true" aria-labelledby="account-modal-title">
        <button class="account-modal-close" type="button" data-account-close aria-label="Закрыть"></button>
        <div class="account-modal-body">
            <h2 id="account-modal-title">Войти или создать профиль</h2>
            <div class="account-modal-notices" aria-live="polite"></div>

            <div class="account-email-step" data-account-email-step>
                <label for="account-email">Эл. почта</label>
                <input id="account-email" type="email" inputmode="email" autocomplete="email" placeholder="Введите эл. почту" value="<?php echo esc_attr($posted_login ?: $posted_email); ?>" required>
                <p class="account-email-error" data-account-error hidden>Введите корректный адрес электронной почты</p>
                <button type="button" data-account-continue>Войти</button>
            </div>

            <form class="account-auth-form account-login-form" data-account-login method="post" action="<?php echo esc_url($account_url); ?>" hidden>
                <label for="account-login-email">Эл. почта</label>
                <input id="account-login-email" name="username" type="email" inputmode="email" autocomplete="username" value="<?php echo esc_attr($posted_login); ?>" required>
                <label for="account-login-password">Пароль</label>
                <input id="account-login-password" name="password" type="password" autocomplete="current-password" required>
                <input type="hidden" name="redirect" value="<?php echo esc_url($account_url); ?>">
                <input type="hidden" id="account-modal-login-nonce" name="woocommerce-login-nonce" value="<?php echo esc_attr(wp_create_nonce('woocommerce-login')); ?>">
                <button type="submit" name="login" value="Войти">Войти</button>
                <div class="account-auth-links">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Забыли пароль?</a>
                    <button type="button" data-account-show-register>Создать профиль</button>
                </div>
            </form>

            <form class="account-auth-form account-register-form" data-account-register method="post" action="<?php echo esc_url($account_url); ?>" hidden>
                <label for="account-register-email">Эл. почта</label>
                <input id="account-register-email" name="email" type="email" inputmode="email" autocomplete="email" value="<?php echo esc_attr($posted_email); ?>" required>
                <label for="account-register-password">Придумайте пароль</label>
                <input id="account-register-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                <input type="hidden" name="redirect" value="<?php echo esc_url($account_url); ?>">
                <input type="hidden" id="account-modal-register-nonce" name="woocommerce-register-nonce" value="<?php echo esc_attr(wp_create_nonce('woocommerce-register')); ?>">
                <button type="submit" name="register" value="Создать профиль">Создать профиль</button>
                <div class="account-auth-links"><button type="button" data-account-show-login>Уже есть профиль</button></div>
            </form>
        </div>
    </section>
</div>

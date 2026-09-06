<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Accounts;

final class EmailVerification
{
    private const PENDING = '_theobroma_email_pending';
    private const TOKEN = '_theobroma_email_token';

    public function register(): void
    {
        add_action('woocommerce_created_customer', [$this, 'created'], 1, 3);
        add_filter('woocommerce_registration_auth_new_customer', [$this, 'registrationAuth'], 99, 2);
        add_filter('authenticate', [$this, 'authenticate'], 99);
        add_filter('send_auth_cookies', [$this, 'authCookies'], 99, 6);
        add_action('wp_loaded', [$this, 'handleConfirmation'], 15);
        add_action('admin_post_nopriv_theobroma_resend_confirmation', [$this, 'resend']);
        add_action('admin_post_theobroma_resend_confirmation', [$this, 'resend']);
        add_action('woocommerce_before_customer_login_form', [$this, 'resendForm']);
        add_action('admin_notices', [$this, 'adminNotice']);
    }

    public function enabled(): bool
    {
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $host = ($settings['smtp_enabled'] ?? 'no') === 'yes' && !empty($settings['smtp_host'])
            ? (string) $settings['smtp_host'] : (string) getenv('THEOBROMA_SMTP_HOST');
        // Do not lock new customers out while mail only reaches the development inbox.
        $ready = $host !== '' && !in_array(strtolower(trim($host)), ['mailpit', 'mailhog', 'localhost', '127.0.0.1', '::1'], true);
        return (bool) apply_filters('theobroma_email_verification_enabled', $ready);
    }

    public function pending(int $id): bool
    {
        return (bool) get_user_meta($id, self::PENDING, true);
    }

    public function created(int $id, array $data = [], bool $generated = false): void
    {
        if (!$this->enabled() || (is_admin() && !wp_doing_ajax())) return;
        update_user_meta($id, self::PENDING, '1');
        $this->send($id);
    }

    public function registrationAuth(bool $allowed, int $id): bool
    {
        if (!$this->pending($id)) return $allowed;
        wc_clear_notices();
        $sent = get_user_meta($id, '_theobroma_email_sent', true);
        wc_add_notice($sent
            ? 'Подтвердите почту: мы отправили письмо со ссылкой. Ссылка действует 24 часа.'
            : 'Аккаунт создан, но письмо не удалось отправить. Запросите его повторно.', $sent ? 'notice' : 'error');
        wc_add_notice('<a href="' . esc_url(add_query_arg('verify-email', '1', wc_get_page_permalink('myaccount'))) . '">Отправить письмо повторно</a>', 'notice');
        unset($_POST['register']);
        return false;
    }

    public function authenticate($user)
    {
        if ($user instanceof \WP_User && $this->pending((int) $user->ID)) {
            return new \WP_Error('email_not_confirmed', 'Подтвердите почту по ссылке из письма. <a href="' . esc_url(add_query_arg('verify-email', '1', wc_get_page_permalink('myaccount'))) . '">Отправить письмо повторно</a>');
        }
        return $user;
    }

    public function authCookies(bool $send, int $expire, int $expiration, int $id, string $scheme, string $token): bool
    {
        return $send && !$this->pending($id);
    }

    public function send(int $id): bool
    {
        $user = get_userdata($id);
        if (!$user || !$this->pending($id)) return false;
        $last = (int) get_user_meta($id, '_theobroma_email_attempt', true);
        if ($last && time() - $last < 60) return false;
        // Compare-and-swap prevents concurrent resend requests rotating each other's links.
        if ($last) {
            if (!update_user_meta($id, '_theobroma_email_attempt', time(), $last)) return false;
        } elseif (!add_user_meta($id, '_theobroma_email_attempt', time(), true)) return false;
        $token = bin2hex(random_bytes(32));
        $record = ['hash' => hash('sha256', $token), 'expires' => time() + DAY_IN_SECONDS, 'email' => $user->user_email];
        update_user_meta($id, self::TOKEN, $record);
        $url = add_query_arg(['confirm-email' => $id, 'confirmation-token' => $token], wc_get_page_permalink('myaccount'));
        $message = "Здравствуйте!\n\nПодтвердите электронную почту для аккаунта Theobroma:\n" . $url
            . "\n\nСсылка действует 24 часа. После подтверждения войдите с вашим паролем. Если вы не задавали пароль, воспользуйтесь восстановлением пароля на странице входа.\n\nЕсли вы не регистрировались, просто проигнорируйте это письмо.\n\nTheobroma — Пища богов";
        $sent = wp_mail($user->user_email, 'Подтвердите почту — Theobroma', $message, ['Content-Type: text/plain; charset=UTF-8']);
        update_user_meta($id, '_theobroma_email_sent', $sent ? '1' : '');
        return $sent;
    }

    public function confirm(int $id, string $token): bool
    {
        if (!$this->pending($id) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
        $record = get_user_meta($id, self::TOKEN, true);
        $user = get_userdata($id);
        if (!is_array($record) || !$user || ($record['email'] ?? '') !== $user->user_email
            || (int) ($record['expires'] ?? 0) <= time()
            || !hash_equals((string) ($record['hash'] ?? ''), hash('sha256', $token))) return false;
        // Consume exactly the record checked above; an old/replayed link cannot confirm.
        if (!delete_user_meta($id, self::TOKEN, $record)) return false;
        delete_user_meta($id, self::PENDING);
        update_user_meta($id, '_theobroma_email_verified_at', time());
        return true;
    }

    public function handleConfirmation(): void
    {
        if (!isset($_GET['confirm-email'], $_GET['confirmation-token'])) return;
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        $id = is_scalar($_GET['confirm-email']) ? absint($_GET['confirm-email']) : 0;
        $token = is_string($_GET['confirmation-token']) ? wp_unslash($_GET['confirmation-token']) : '';
        $confirmed = $this->confirm($id, $token);
        wc_add_notice($confirmed ? 'Почта подтверждена. Теперь можно войти в аккаунт.' : 'Ссылка недействительна или истекла. Запросите новое письмо.', $confirmed ? 'success' : 'error');
        wp_safe_redirect($confirmed ? wc_get_page_permalink('myaccount') : add_query_arg('verify-email', '1', wc_get_page_permalink('myaccount')));
        exit;
    }

    public function resend(): void
    {
        check_admin_referer('theobroma_resend_confirmation');
        if (!WC()->session) WC()->initialize_session();
        WC()->session->set_customer_session_cookie(true);
        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'theobroma_verify_' . hash_hmac('sha256', $ip, wp_salt());
        $attempts = (int) get_transient($key);
        if ($attempts < 5) {
            set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
            $user = get_user_by('email', $email);
            if ($user) $this->send((int) $user->ID);
        }
        // Same response for unknown, verified and throttled accounts.
        wc_add_notice('Если почта ожидает подтверждения, письмо будет отправлено. Проверьте входящие и спам. Повторный запрос доступен через минуту.', 'notice');
        wp_safe_redirect(add_query_arg('verify-email', '1', wc_get_page_permalink('myaccount')));
        exit;
    }

    public function resendForm(): void
    {
        if (!isset($_GET['verify-email'])) return;
        echo '<section class="account-page-auth"><h2>Подтверждение почты</h2><p>Введите почту, указанную при регистрации.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="theobroma_resend_confirmation">';
        wp_nonce_field('theobroma_resend_confirmation');
        echo '<p class="form-row"><label for="confirmation-email">Электронная почта</label><input id="confirmation-email" name="email" type="email" autocomplete="email" required></p><p><button class="button" type="submit">Отправить письмо</button></p></form></section>';
    }

    public function adminNotice(): void
    {
        if (!current_user_can('manage_woocommerce') || $this->enabled()) return;
        echo '<div class="notice notice-warning"><p>Подтверждение почты при регистрации подготовлено, но не включено: настройте внешний SMTP в WooCommerce → Интеграции → Почта SMTP. Сейчас письма не доставляются покупателям.</p></div>';
    }
}

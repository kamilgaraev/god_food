<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Ozon\OzonAuthorizationUrl;
use Theobroma\Commerce\Integrations\Ozon\WordPressOAuthStateStore;
use Theobroma\Commerce\Rest\OzonOAuthCallbackController;

final class OzonAuthorizationAction
{
    public const ACTION = 'theobroma_ozon_authorize';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Недостаточно прав для авторизации Ozon.', 'theobroma-commerce'));
        }
        check_admin_referer(self::ACTION);

        $settings = get_option('theobroma_commerce_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $clientId = defined('THEOBROMA_OZON_CLIENT_ID')
            ? (string) constant('THEOBROMA_OZON_CLIENT_ID')
            : (string) ($settings['ozon_client_id'] ?? '');
        $clientSecret = defined('THEOBROMA_OZON_CLIENT_SECRET')
            ? (string) constant('THEOBROMA_OZON_CLIENT_SECRET')
            : (string) ($settings['ozon_client_secret'] ?? '');
        if (trim($clientId) === '' || trim($clientSecret) === '') {
            $this->notice('error', 'Сначала сохраните Client ID и Secret частного приложения Ozon.');
            $this->returnToSettings();
        }

        $state = (new WordPressOAuthStateStore())->issue(get_current_user_id());
        $url = (new OzonAuthorizationUrl())->build(
            $clientId,
            OzonOAuthCallbackController::redirectUri(),
            $state
        );

        wp_redirect($url, 302, 'Theobroma Commerce');
        exit;
    }

    private function notice(string $status, string $message): void
    {
        set_transient(
            OzonConnectionAction::NOTICE_PREFIX . get_current_user_id(),
            compact('status', 'message'),
            60
        );
    }

    private function returnToSettings(): never
    {
        wp_safe_redirect(admin_url('admin.php?page=theobroma-commerce'));
        exit;
    }
}

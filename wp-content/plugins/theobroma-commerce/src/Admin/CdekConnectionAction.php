<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore;

final class CdekConnectionAction
{
    public const ACTION = 'theobroma_cdek_test_connection';
    public const NOTICE_PREFIX = 'theobroma_cdek_connection_notice_';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Недостаточно прав для проверки СДЭК.', 'theobroma-commerce'));
        }
        check_admin_referer(self::ACTION);

        $settings = get_option('theobroma_commerce_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $clientId = (string) ($settings['cdek_client_id'] ?? '');
        $clientSecret = defined('THEOBROMA_CDEK_CLIENT_SECRET')
            ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET')
            : (string) ($settings['cdek_client_secret'] ?? '');
        $client = new CdekClient(
            new WpTransport(),
            new WordPressTokenStore(),
            $clientId,
            $clientSecret
        );
        $result = (new CdekConnectionChecker())->check($client);
        set_transient(self::NOTICE_PREFIX . get_current_user_id(), $result, 60);

        wp_safe_redirect(admin_url('admin.php?page=theobroma-commerce'));
        exit;
    }
}

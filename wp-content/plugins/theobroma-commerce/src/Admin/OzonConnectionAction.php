<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;

final class OzonConnectionAction
{
    public const ACTION = 'theobroma_ozon_test_connection';
    public const NOTICE_PREFIX = 'theobroma_ozon_connection_notice_';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Недостаточно прав для проверки Ozon.', 'theobroma-commerce'));
        }
        check_admin_referer(self::ACTION);

        $settings = get_option('theobroma_commerce_settings', []);
        $factory = new OzonClientFactory(new WpTransport(), new WordPressTokenStore());
        $result = (new OzonConnectionChecker())->check(
            $factory->authenticatorFromSettings(is_array($settings) ? $settings : [])
        );
        set_transient(self::NOTICE_PREFIX . get_current_user_id(), $result, 60);

        wp_safe_redirect(admin_url('admin.php?page=theobroma-commerce'));
        exit;
    }
}

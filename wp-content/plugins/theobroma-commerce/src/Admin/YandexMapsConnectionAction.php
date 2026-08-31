<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

final class YandexMapsConnectionAction
{
    public const ACTION = 'theobroma_yandex_maps_test_connection';
    public const NOTICE_PREFIX = 'theobroma_yandex_maps_notice_';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Недостаточно прав для проверки Яндекс Карт.', 'theobroma-commerce'));
        }
        check_admin_referer(self::ACTION);

        $settings = (array) get_option('theobroma_commerce_settings', []);
        $jsKey = defined('THEOBROMA_YANDEX_MAPS_JS_KEY')
            ? (string) constant('THEOBROMA_YANDEX_MAPS_JS_KEY')
            : (string) ($settings['yandex_maps_js_key'] ?? '');
        $geocoderKey = defined('THEOBROMA_YANDEX_GEOCODER_KEY')
            ? (string) constant('THEOBROMA_YANDEX_GEOCODER_KEY')
            : (string) ($settings['yandex_geocoder_key'] ?? '');

        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            (new YandexMapsConnectionChecker())->check($jsKey, $geocoderKey),
            60
        );
        wp_safe_redirect(admin_url('admin.php?page=theobroma-commerce'));
        exit;
    }
}

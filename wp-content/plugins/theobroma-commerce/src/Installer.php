<?php

declare(strict_types=1);

namespace Theobroma\Commerce;

use Theobroma\Commerce\Admin\Settings;
use Theobroma\Commerce\Loyalty\LoyaltySchema;

final class Installer
{
    public static function activate(): void
    {
        global $wpdb;

        $current = get_option('theobroma_commerce_settings', []);
        if (!is_array($current)) {
            $current = [];
        }
        update_option('theobroma_commerce_settings', array_merge((new Settings())->defaults(), $current), false);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta((new LoyaltySchema($wpdb->prefix))->statements($wpdb->get_charset_collate()));

        if (!class_exists('WC_Shipping_Zone')) {
            return;
        }
        $zone = new \WC_Shipping_Zone(0);
        foreach ($zone->get_shipping_methods(true) as $method) {
            if ($method->id === 'theobroma_cdek') {
                return;
            }
        }
        $zone->add_shipping_method('theobroma_cdek');
    }
}

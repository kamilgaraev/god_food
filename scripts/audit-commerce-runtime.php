<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$settings = (array) get_option('theobroma_commerce_settings', []);
$analytics = (array) get_option('theobroma_analytics_settings', []);
$gateways = WC()->payment_gateways()->payment_gateways();

$gatewayStatuses = [];
foreach ($gateways as $id => $gateway) {
    $gatewayStatuses[(string) $id] = [
        'enabled' => (string) $gateway->enabled,
        'title' => wp_strip_all_tags((string) $gateway->get_title()),
    ];
}

$output = [
    'cdek' => [
        'enabled' => ($settings['cdek_enabled'] ?? 'no') === 'yes',
        'client_id_set' => trim((string) ($settings['cdek_client_id'] ?? '')) !== '',
        'secret_set' => defined('THEOBROMA_CDEK_CLIENT_SECRET')
            || trim((string) ($settings['cdek_client_secret'] ?? '')) !== '',
        'sender_city_code_set' => (int) ($settings['cdek_sender_city_code'] ?? 0) > 0,
    ],
    'ozon' => [
        'enabled' => ($settings['ozon_enabled'] ?? 'no') === 'yes',
        'approved' => ($settings['ozon_approved'] ?? 'no') === 'yes',
        'token_set' => defined('THEOBROMA_OZON_ACCESS_TOKEN')
            || trim((string) ($settings['ozon_access_token'] ?? '')) !== '',
        'products_mapped_confirmed' => ($settings['ozon_products_mapped'] ?? 'no') === 'yes',
        'live_test_completed' => ($settings['ozon_live_test_completed'] ?? 'no') === 'yes',
    ],
    'payment_gateways' => $gatewayStatuses,
    'analytics' => [
        'yandex_metrika_counter_set' => preg_match('/^[1-9][0-9]{0,14}$/', (string) ($analytics['counter_id'] ?? '')) === 1,
        'consent_gated' => true,
    ],
    'active_plugins' => array_values((array) get_option('active_plugins', [])),
];

echo wp_json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Rest;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore;

final class CdekPointsController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('theobroma-commerce/v1', '/cdek/points', [
            'methods' => 'GET',
            'callback' => [$this, 'points'],
            'permission_callback' => '__return_true',
            'args' => ['city' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']],
        ]);
    }

    public function points(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET') ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET') : (string) ($settings['cdek_client_secret'] ?? '');
        if (($settings['cdek_enabled'] ?? 'no') !== 'yes' || ($settings['cdek_client_id'] ?? '') === '' || $secret === '') {
            return new \WP_Error('cdek_not_configured', __('СДЭК ещё не настроен.', 'theobroma-commerce'), ['status' => 503]);
        }

        $city = (string) $request->get_param('city');
        $cacheKey = 'theobroma_cdek_points_' . md5(mb_strtolower($city));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return rest_ensure_response($cached);
        }

        try {
            $client = new CdekClient(new WpTransport(), new WordPressTokenStore(), (string) $settings['cdek_client_id'], $secret);
            $cities = $client->cities(['city' => $city, 'country_codes' => 'RU', 'size' => 1]);
            $cityCode = (int) ($cities[0]['code'] ?? 0);
            if ($cityCode <= 0) {
                return new \WP_Error('cdek_city_not_found', __('Город не найден в СДЭК.', 'theobroma-commerce'), ['status' => 404]);
            }
            $points = array_map(static fn (array $point): array => [
                'code' => (string) ($point['code'] ?? ''),
                'name' => (string) ($point['name'] ?? ''),
                'address' => (string) ($point['location']['address_full'] ?? $point['location']['address'] ?? ''),
                'work_time' => (string) ($point['work_time'] ?? ''),
            ], $client->deliveryPoints(['city_code' => $cityCode, 'type' => 'PVZ', 'is_handout' => 1]));
            $points = array_values(array_filter($points, static fn (array $point): bool => $point['code'] !== ''));
            set_transient($cacheKey, $points, HOUR_IN_SECONDS);
            return rest_ensure_response($points);
        } catch (\Throwable $exception) {
            wc_get_logger()->error($exception->getMessage(), ['source' => 'theobroma-cdek']);
            return new \WP_Error('cdek_unavailable', __('Не удалось загрузить пункты СДЭК.', 'theobroma-commerce'), ['status' => 502]);
        }
    }
}

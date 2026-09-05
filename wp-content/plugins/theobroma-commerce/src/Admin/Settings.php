<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

final class Settings
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'cdek_enabled' => 'no',
            'cdek_client_id' => '',
            'cdek_client_secret' => '',
            'cdek_sender_city_code' => 0,
            'cdek_sender_address' => '',
            'cdek_order_status' => 'processing',
            'ozon_client_id' => '',
            'ozon_client_secret' => '',
            'map_provider' => 'yandex',
            'yandex_maps_js_key' => '',
            'yandex_suggest_key' => '',
            'yandex_geocoder_key' => '',
        ];
    }

    /** @param array<string, mixed> $input
     *  @param array<string, mixed> $existing
     *  @return array<string, mixed>
     */
    public function sanitize(array $input, array $existing = []): array
    {
        $result = $this->defaults();
        $result['map_provider'] = ($input['map_provider'] ?? $existing['map_provider'] ?? 'yandex') === 'osm' ? 'osm' : 'yandex';
        foreach (['cdek_enabled'] as $flag) {
            $result[$flag] = in_array((string) ($input[$flag] ?? ''), ['1', 'yes', 'on', 'true'], true) ? 'yes' : 'no';
        }

        $result['cdek_client_id'] = trim((string) ($input['cdek_client_id'] ?? ''));
        $result['ozon_client_id'] = trim((string) ($input['ozon_client_id'] ?? ''));
        $result['yandex_maps_js_key'] = trim((string) ($input['yandex_maps_js_key'] ?? ''));
        $result['cdek_sender_city_code'] = max(0, (int) ($input['cdek_sender_city_code'] ?? 0));
        $result['cdek_sender_address'] = trim((string) ($input['cdek_sender_address'] ?? ''));
        $result['cdek_order_status'] = preg_replace('/[^a-z0-9_-]/', '', (string) ($input['cdek_order_status'] ?? 'processing')) ?: 'processing';

        foreach (['cdek_client_secret', 'ozon_client_secret', 'yandex_suggest_key', 'yandex_geocoder_key'] as $secret) {
            $newValue = trim((string) ($input[$secret] ?? ''));
            $result[$secret] = $newValue !== '' ? $newValue : (string) ($existing[$secret] ?? '');
        }

        return $result;
    }

    /** @param array<string,mixed> $existing @param array<string,mixed> $next */
    public function ozonCredentialsChanged(array $existing, array $next): bool
    {
        return (string) ($existing['ozon_client_id'] ?? '') !== (string) ($next['ozon_client_id'] ?? '')
            || (string) ($existing['ozon_client_secret'] ?? '') !== (string) ($next['ozon_client_secret'] ?? '');
    }
}

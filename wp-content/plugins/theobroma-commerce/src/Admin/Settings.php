<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

final class Settings
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'smtp_enabled' => 'no',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_address' => '',
            'smtp_from_name' => '',
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

        $result['smtp_enabled'] = ($input['smtp_enabled'] ?? '') === 'yes' ? 'yes' : 'no';
        $host = trim((string) ($input['smtp_host'] ?? ''));
        $result['smtp_host'] = preg_match('/\A[a-zA-Z0-9.-]+\z/', $host) ? $host : '';
        $result['smtp_port'] = max(1, min(65535, (int) ($input['smtp_port'] ?? 587)));
        $result['smtp_encryption'] = in_array($input['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $input['smtp_encryption'] : 'tls';
        foreach (['smtp_username', 'smtp_from_name'] as $key) {
            $result[$key] = trim(strip_tags(str_replace(["\r", "\n"], '', (string) ($input[$key] ?? ''))));
        }
        $email = trim((string) ($input['smtp_from_address'] ?? ''));
        $result['smtp_from_address'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $password = (string) ($input['smtp_password'] ?? '');
        $result['smtp_password'] = $password !== '' ? $password : (string) ($existing['smtp_password'] ?? '');
        if ($result['smtp_enabled'] === 'yes' && ($result['smtp_host'] === '' || $result['smtp_from_address'] === '')) {
            $result['smtp_enabled'] = 'no';
            if (function_exists('add_settings_error')) {
                add_settings_error('theobroma_commerce_settings', 'smtp_required', 'SMTP не включён: укажите корректный сервер и email отправителя.');
            }
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

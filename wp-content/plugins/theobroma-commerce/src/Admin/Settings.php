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
            'ozon_enabled' => 'no',
            'ozon_approved' => 'no',
            'ozon_access_token' => '',
            'ozon_products_mapped' => 'no',
            'ozon_live_test_completed' => 'no',
        ];
    }

    /** @param array<string, mixed> $input
     *  @param array<string, mixed> $existing
     *  @return array<string, mixed>
     */
    public function sanitize(array $input, array $existing = []): array
    {
        $result = $this->defaults();
        foreach (['cdek_enabled', 'ozon_enabled', 'ozon_approved', 'ozon_products_mapped', 'ozon_live_test_completed'] as $flag) {
            $result[$flag] = in_array((string) ($input[$flag] ?? ''), ['1', 'yes', 'on', 'true'], true) ? 'yes' : 'no';
        }

        $result['cdek_client_id'] = trim((string) ($input['cdek_client_id'] ?? ''));
        $result['cdek_sender_city_code'] = max(0, (int) ($input['cdek_sender_city_code'] ?? 0));
        $result['cdek_sender_address'] = trim((string) ($input['cdek_sender_address'] ?? ''));
        $result['cdek_order_status'] = preg_replace('/[^a-z0-9_-]/', '', (string) ($input['cdek_order_status'] ?? 'processing')) ?: 'processing';

        foreach (['cdek_client_secret', 'ozon_access_token'] as $secret) {
            $newValue = trim((string) ($input[$secret] ?? ''));
            $result[$secret] = $newValue !== '' ? $newValue : (string) ($existing[$secret] ?? '');
        }

        return $result;
    }
}

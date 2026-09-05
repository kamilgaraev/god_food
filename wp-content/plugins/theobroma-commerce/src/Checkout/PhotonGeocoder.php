<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class PhotonGeocoder
{
    private $request;

    public function __construct(?callable $request = null)
    {
        $this->request = $request ?? static function (string $url): array {
            $key = 'theobroma_photon_' . md5($url);
            $cached = get_transient($key);
            if (is_array($cached)) {
                return $cached;
            }
            $response = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'limit_response_size' => 262144,
                'user-agent' => 'TheobromaDelivery/1.0 (' . home_url('/') . ')']);
            if (is_wp_error($response)) {
                throw new \RuntimeException('Поиск адресов временно недоступен.');
            }
            $result = ['status' => (int) wp_remote_retrieve_response_code($response), 'body' => (string) wp_remote_retrieve_body($response)];
            if ($result['status'] === 200) {
                set_transient($key, $result, 3600);
            }
            return $result;
        };
    }

    public function suggestions(string $query, string $key = '', int $limit = 5): array
    {
        $query = mb_substr(trim($query), 0, 240);
        if (mb_strlen($query) < 3) {
            return [];
        }
        $response = ($this->request)('https://photon.komoot.io/api/?' . http_build_query([
            'q' => $query, 'limit' => max(1, min(7, $limit)),
        ], '', '&', PHP_QUERY_RFC3986));
        if (($response['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Не удалось загрузить подсказки адреса.');
        }
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body['features'] ?? null)) {
            throw new \RuntimeException('Некорректный ответ поиска адресов.');
        }
        $result = [];
        foreach ($body['features'] as $feature) {
            $p = $feature['properties'] ?? [];
            $c = $feature['geometry']['coordinates'] ?? [];
            if (!is_numeric($c[0] ?? null) || !is_numeric($c[1] ?? null) || abs((float) $c[0]) > 180 || abs((float) $c[1]) > 90) {
                continue;
            }
            $lon = (float) $c[0];
            $lat = (float) $c[1];
            $city = (string) ($p['city'] ?? $p['town'] ?? $p['village'] ?? (in_array($p['osm_value'] ?? '', ['city', 'town', 'village', 'hamlet'], true) ? ($p['name'] ?? '') : ''));
            $address = trim(implode(', ', array_filter([(string) ($p['street'] ?? ''), (string) ($p['housenumber'] ?? '')])));
            $label = implode(', ', array_unique(array_filter([$address, (string) ($p['name'] ?? ''), $city, (string) ($p['state'] ?? ''), (string) ($p['country'] ?? '')])));
            if ($label === '') {
                continue;
            }
            // Use a neighbourhood around the result, not an entire country's extent.
            $result[] = ['label' => $label, 'city' => $city, 'address' => $address,
                'postcode' => (string) ($p['postcode'] ?? ''), 'latitude' => $lat, 'longitude' => $lon,
                'house' => (string) ($p['housenumber'] ?? ''),
                'viewport' => ['left_bottom' => ['lat' => max(-90, $lat - 0.05), 'long' => max(-180, $lon - 0.08)],
                    'right_top' => ['lat' => min(90, $lat + 0.05), 'long' => min(180, $lon + 0.08)]]];
        }
        return $result;
    }

    public function coordinates(string $address, string $key = ''): array
    {
        $first = $this->suggestions($address, '', 1)[0] ?? null;
        if ($first === null || $first['house'] === '') {
            throw new \RuntimeException('Не найден точный адрес дома. Уточните улицу и номер дома или выберите пункт выдачи.');
        }
        return ['latitude' => $first['latitude'], 'longitude' => $first['longitude']];
    }
}

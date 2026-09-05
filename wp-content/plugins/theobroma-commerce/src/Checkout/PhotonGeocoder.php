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
        return $this->search($query, '', false, $limit);
    }

    public function search(string $query, string $country = '', bool $citiesOnly = false, int $limit = 5): array
    {
        $query = mb_substr(trim($query), 0, 240);
        // Apartment/office details are for the courier, not OSM building search.
        $query = preg_replace('/(?:,\s*|\s+)(?:офис|оф\.|квартира|кв\.|подъезд|этаж)\s*\S.*$/ui', '', $query);
        $query = preg_replace('/(?<![\p{L}\p{N}])(?:г\.?|город|ул\.?|улица|д\.?|дом|республика|респ\.)(?=\s|,|$)/ui', ' ', $query);
        $parts = array_unique(array_filter(array_map('trim', explode(',', $query))));
        $query = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
        if (mb_strlen($query) < ($citiesOnly ? 2 : 3)) {
            return [];
        }
        $params = ['q' => $query, 'limit' => max(1, min(7, $limit))];
        $country = strtoupper(trim($country));
        if (preg_match('/^[A-Z]{2}$/D', $country)) $params['countrycode'] = $country;
        $url = 'https://photon.komoot.io/api/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($citiesOnly) $url .= '&osm_tag=place:city&osm_tag=place:town&osm_tag=place:village&osm_tag=place:hamlet';
        $response = ($this->request)($url);
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
            if ($country !== '' && strtoupper((string) ($p['countrycode'] ?? '')) !== $country) continue;
            if ($citiesOnly && !in_array($p['osm_value'] ?? '', ['city', 'town', 'village', 'hamlet'], true)) continue;
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
            // A house search must stay local: Ozon caps each viewport response at 100 points.
            $latRadius = !empty($p['housenumber']) ? 0.01 : 0.05;
            $lonRadius = !empty($p['housenumber']) ? 0.015 : 0.08;
            $result[] = ['label' => $label, 'city' => $city, 'address' => $address,
                'postcode' => (string) ($p['postcode'] ?? ''), 'latitude' => $lat, 'longitude' => $lon,
                'house' => (string) ($p['housenumber'] ?? ''),
                'viewport' => ['left_bottom' => ['lat' => max(-90, $lat - $latRadius), 'long' => max(-180, $lon - $lonRadius)],
                    'right_top' => ['lat' => min(90, $lat + $latRadius), 'long' => min(180, $lon + $lonRadius)]]];
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

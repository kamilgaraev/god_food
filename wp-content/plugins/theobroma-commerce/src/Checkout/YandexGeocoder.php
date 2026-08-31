<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class YandexGeocoder
{
    /** @return array{latitude:float,longitude:float} */
    public function coordinates(string $address, string $key): array
    {
        if (trim($address) === '' || trim($key) === '') {
            throw new \InvalidArgumentException('Для курьерской доставки требуется настроенный геокодер.');
        }
        $url = add_query_arg([
            'format' => 'json',
            'results' => 1,
            'geocode' => $address,
            'apikey' => $key,
        ], 'https://geocode-maps.yandex.ru/1.x/');
        $response = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new \RuntimeException('Не удалось определить координаты адреса.');
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $position = $body['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'] ?? '';
        $parts = preg_split('/\s+/', trim((string) $position)) ?: [];
        if (count($parts) !== 2) {
            throw new \RuntimeException('Адрес не найден на карте.');
        }
        return ['latitude' => (float) $parts[1], 'longitude' => (float) $parts[0]];
    }
}

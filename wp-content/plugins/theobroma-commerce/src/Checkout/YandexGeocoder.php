<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class YandexGeocoder
{
    /** @var callable(string):array{status:int,body:string} */
    private $request;

    /** @param null|callable(string):array{status:int,body:string} $request */
    public function __construct(?callable $request = null)
    {
        $this->request = $request ?? static function (string $url): array {
            $response = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0]);
            if (is_wp_error($response)) {
                return ['status' => 0, 'body' => ''];
            }
            return [
                'status' => (int) wp_remote_retrieve_response_code($response),
                'body' => (string) wp_remote_retrieve_body($response),
            ];
        };
    }

    /** @return array{latitude:float,longitude:float} */
    public function coordinates(string $address, string $key): array
    {
        if (trim($address) === '' || trim($key) === '') {
            throw new \InvalidArgumentException('Для курьерской доставки требуется настроенный геокодер.');
        }
        $response = ($this->request)($this->url([
            'format' => 'json',
            'results' => 1,
            'geocode' => $address,
            'apikey' => $key,
        ]));
        if (($response['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Не удалось определить координаты адреса.');
        }
        $body = json_decode((string) ($response['body'] ?? ''), true);
        $position = $this->pair((string) ($body['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'] ?? ''));
        if ($position === null) {
            throw new \RuntimeException('Адрес не найден на карте.');
        }
        return ['latitude' => $position['lat'], 'longitude' => $position['long']];
    }

    /** @return list<array{label:string,city:string,postcode:string,address:string,latitude:float,longitude:float,viewport:array<string,array{lat:float,long:float}>}> */
    public function suggestions(string $query, string $key, int $limit = 5): array
    {
        if (mb_strlen(trim($query)) < 3 || trim($key) === '') {
            return [];
        }
        $response = ($this->request)($this->url([
            'format' => 'json',
            'results' => max(1, min(7, $limit)),
            'geocode' => trim($query),
            'apikey' => trim($key),
        ]));
        if (($response['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Не удалось загрузить подсказки адреса.');
        }
        $body = json_decode((string) ($response['body'] ?? ''), true);
        $members = (array) ($body['response']['GeoObjectCollection']['featureMember'] ?? []);
        $result = [];
        foreach ($members as $member) {
            $object = is_array($member['GeoObject'] ?? null) ? $member['GeoObject'] : [];
            $position = $this->pair((string) ($object['Point']['pos'] ?? ''));
            $lower = $this->pair((string) ($object['boundedBy']['Envelope']['lowerCorner'] ?? ''));
            $upper = $this->pair((string) ($object['boundedBy']['Envelope']['upperCorner'] ?? ''));
            if ($position === null || $lower === null || $upper === null) {
                continue;
            }
            $label = trim(implode(', ', array_filter([
                (string) ($object['name'] ?? ''),
                (string) ($object['description'] ?? ''),
            ])));
            if ($label === '') {
                continue;
            }
            $addressMeta = is_array($object['metaDataProperty']['GeocoderMetaData']['Address'] ?? null)
                ? $object['metaDataProperty']['GeocoderMetaData']['Address']
                : [];
            $components = [];
            foreach ((array) ($addressMeta['Components'] ?? []) as $component) {
                $kind = (string) ($component['kind'] ?? '');
                $name = trim((string) ($component['name'] ?? ''));
                if ($kind !== '' && $name !== '') {
                    $components[$kind] = $name;
                }
            }
            $streetAddress = trim(implode(', ', array_filter([
                (string) ($components['street'] ?? ''),
                (string) ($components['house'] ?? ''),
            ])));
            $result[] = [
                'label' => $label,
                'city' => (string) ($components['locality'] ?? ''),
                'postcode' => trim((string) ($addressMeta['postal_code'] ?? '')),
                'address' => $streetAddress,
                'latitude' => $position['lat'],
                'longitude' => $position['long'],
                'viewport' => [
                    'left_bottom' => ['lat' => $lower['lat'], 'long' => $lower['long']],
                    'right_top' => ['lat' => $upper['lat'], 'long' => $upper['long']],
                ],
            ];
        }
        return $result;
    }

    /** @return null|array{lat:float,long:float} */
    private function pair(string $value): ?array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }
        return ['lat' => (float) $parts[1], 'long' => (float) $parts[0]];
    }

    /** @param array<string,int|string> $query */
    private function url(array $query): string
    {
        return 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

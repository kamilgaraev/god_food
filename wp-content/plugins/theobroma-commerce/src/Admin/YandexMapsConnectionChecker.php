<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

final class YandexMapsConnectionChecker
{
    /** @var callable(string):array{status:int,body:string} */
    private $javascriptProbe;

    /** @var callable(string):array{status:int,body:string} */
    private $geocoderProbe;

    /**
     * @param null|callable(string):array{status:int,body:string} $javascriptProbe
     * @param null|callable(string):array{status:int,body:string} $geocoderProbe
     */
    public function __construct(?callable $javascriptProbe = null, ?callable $geocoderProbe = null)
    {
        $this->javascriptProbe = $javascriptProbe ?? static function (string $key): array {
            return self::request('https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=' . rawurlencode($key));
        };
        $this->geocoderProbe = $geocoderProbe ?? static function (string $key): array {
            return self::request('https://geocode-maps.yandex.ru/1.x/?format=json&results=1&geocode=' . rawurlencode('Москва') . '&apikey=' . rawurlencode($key));
        };
    }

    /** @return array{javascript:array{status:string,message:string},geocoder:array{status:string,message:string},list_fallback:bool} */
    public function check(string $javascriptKey, string $geocoderKey): array
    {
        $javascript = $this->probe(trim($javascriptKey), $this->javascriptProbe, 'JavaScript API');
        $geocoder = $this->probe(trim($geocoderKey), $this->geocoderProbe, 'HTTP Геокодер');

        return [
            'javascript' => $javascript,
            'geocoder' => $geocoder,
            'list_fallback' => $javascript['status'] !== 'valid' || $geocoder['status'] !== 'valid',
        ];
    }

    /** @param callable(string):array{status:int,body:string} $probe @return array{status:string,message:string} */
    private function probe(string $key, callable $probe, string $label): array
    {
        if ($key === '') {
            return ['status' => 'not_configured', 'message' => $label . ': ключ не задан, будет использован список без карты.'];
        }

        try {
            $response = $probe($key);
            $body = mb_strtolower((string) ($response['body'] ?? ''));
            if ((int) ($response['status'] ?? 0) !== 200 || str_contains($body, 'invalid apikey') || str_contains($body, 'forbidden')) {
                return ['status' => 'invalid', 'message' => $label . ': ключ отклонён.'];
            }
            return ['status' => 'valid', 'message' => $label . ': подключение работает.'];
        } catch (\Throwable) {
            return ['status' => 'invalid', 'message' => $label . ': не удалось выполнить проверку.'];
        }
    }

    /** @return array{status:int,body:string} */
    private static function request(string $url): array
    {
        $response = wp_safe_remote_get($url, [
            'timeout' => 8,
            'redirection' => 0,
            'user-agent' => 'Theobroma Commerce map diagnostics',
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('Map provider request failed');
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Rest;

use Theobroma\Commerce\Checkout\CdekCheckoutService;
use Theobroma\Commerce\Checkout\CheckoutProductLines;
use Theobroma\Commerce\Checkout\DeliveryRuntime;
use Theobroma\Commerce\Checkout\DeliverySelection;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;
use Theobroma\Commerce\Checkout\OzonCheckoutService;
use Theobroma\Commerce\Checkout\ShippingRateCache;
use Theobroma\Commerce\Checkout\YandexGeocoder;
use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore as CdekTokenStore;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore as OzonTokenStore;
use Theobroma\Commerce\Shipping\CdekPackageBuilder;

final class DeliveryCheckoutController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
        add_action('wc_ajax_theobroma_delivery_quote', [$this, 'ajaxQuote']);
    }

    public function ajaxQuote(): void
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $request = new \WP_REST_Request('POST');
        $request->set_body_params(is_array($decoded) ? $decoded : []);
        $response = $this->quote($request);

        if (is_wp_error($response)) {
            $data = $response->get_error_data();
            $status = is_array($data) ? (int) ($data['status'] ?? 400) : 400;
            wp_send_json([
                'code' => $response->get_error_code(),
                'message' => $response->get_error_message(),
                'data' => $data,
            ], $status);
        }

        wp_send_json($response->get_data(), $response->get_status());
    }

    public function routes(): void
    {
        register_rest_route('theobroma-commerce/v1', '/delivery/points', [
            'methods' => 'GET',
            'callback' => [$this, 'points'],
            'permission_callback' => [$this, 'publicAccess'],
        ]);
        register_rest_route('theobroma-commerce/v1', '/delivery/suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'suggestions'],
            'permission_callback' => [$this, 'publicAccess'],
        ]);
        register_rest_route('theobroma-commerce/v1', '/delivery/quote', [
            'methods' => 'POST',
            'callback' => [$this, 'quote'],
            'permission_callback' => [$this, 'publicAccess'],
        ]);
        register_rest_route('theobroma-commerce/v1', '/delivery/selection', [
            ['methods' => 'GET', 'callback' => [$this, 'selection'], 'permission_callback' => [$this, 'publicAccess']],
            ['methods' => 'DELETE', 'callback' => [$this, 'clear'], 'permission_callback' => [$this, 'publicAccess']],
        ]);
    }

    public function publicAccess(): bool
    {
        return true;
    }

    public function points(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $provider = sanitize_key((string) $request->get_param('provider'));
            $settings = (array) get_option('theobroma_commerce_settings', []);
            if ($provider === 'cdek') {
                $points = $this->cdek($settings)->points(sanitize_text_field((string) $request->get_param('city')));
            } elseif ($provider === 'ozon') {
                $points = $this->ozon($settings)->points($this->viewport($request));
            } else {
                return new \WP_Error('invalid_provider', __('Неизвестная служба доставки.', 'theobroma-commerce'), ['status' => 400]);
            }
            return rest_ensure_response(['points' => $points]);
        } catch (\Throwable $exception) {
            $failure = DeliveryProviderFailure::fromException(
                sanitize_key((string) $request->get_param('provider')),
                $exception
            );
            wc_get_logger()->error('Delivery points unavailable', $failure->logContext());
            return new \WP_Error('delivery_points_unavailable', $failure->publicMessage(), ['status' => 502]);
        }
    }

    public function suggestions(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $key = defined('THEOBROMA_YANDEX_GEOCODER_KEY')
            ? (string) constant('THEOBROMA_YANDEX_GEOCODER_KEY')
            : (string) ($settings['yandex_geocoder_key'] ?? '');
        if (trim($key) === '') {
            return rest_ensure_response(['configured' => false, 'suggestions' => []]);
        }

        try {
            return rest_ensure_response([
                'configured' => true,
                'suggestions' => (new YandexGeocoder())->suggestions(
                    sanitize_text_field((string) $request->get_param('query')),
                    $key
                ),
            ]);
        } catch (\Throwable $exception) {
            wc_get_logger()->error('Address suggestions unavailable', ['source' => 'theobroma-delivery']);
            return new \WP_Error('delivery_suggestions_unavailable', __('Не удалось загрузить подсказки адреса.', 'theobroma-commerce'), ['status' => 502]);
        }
    }

    public function quote(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $provider = sanitize_key((string) $request->get_param('provider'));
            $kind = sanitize_key((string) $request->get_param('kind'));
            if (!in_array($provider, ['cdek', 'ozon'], true) || !in_array($kind, ['pickup', 'courier'], true)) {
                return new \WP_Error('invalid_delivery', __('Выберите службу и способ доставки.', 'theobroma-commerce'), ['status' => 400]);
            }
            if ($provider === 'ozon') {
                $nameError = \Theobroma\Commerce\Checkout\DeliveryCustomerName::error($this->person($request));
                if ($nameError !== null) {
                    return new \WP_Error('invalid_delivery_name', $nameError, ['status' => 422]);
                }
            }
            $package = DeliveryRuntime::currentPackage();
            $contents = (array) $package['contents'];
            $destination = $this->destination($request, (array) $package['destination']);
            $quoteContext = DeliveryRuntime::quoteContext($package, $destination);
            $package = $quoteContext['package'];
            $fingerprint = $quoteContext['fingerprint'];
            $settings = (array) get_option('theobroma_commerce_settings', []);
            $point = [];

            if ($provider === 'cdek') {
                $lines = (new CheckoutProductLines())->cdek($contents);
                $payload = (new CdekPackageBuilder((int) ($settings['cdek_sender_city_code'] ?? 0)))->build([
                    'postal_code' => (string) ($destination['postcode'] ?? ''),
                    'city' => (string) ($destination['city'] ?? ''),
                    'address' => trim((string) ($destination['address'] ?? '') . ' ' . (string) ($destination['address_2'] ?? '')),
                ], $lines);
                $quote = $this->cdek($settings)->quote($payload, $kind);
                if ($kind === 'pickup') {
                    $point = $this->findPoint($this->cdek($settings)->points((string) ($destination['city'] ?? '')), (string) $request->get_param('point_id'));
                }
            } else {
                $ozon = $this->ozon($settings);
                $items = (new CheckoutProductLines())->ozon($contents);
                if ($kind === 'pickup') {
                    $point = $ozon->point(sanitize_text_field((string) $request->get_param('point_id')));
                    $delivery = ['pick_up' => ['map_point_id' => (int) $point['id']]];
                } else {
                    $latitude = $request->get_param('latitude');
                    $longitude = $request->get_param('longitude');
                    if (!is_numeric($latitude) || !is_numeric($longitude)) {
                        $geocoderKey = defined('THEOBROMA_YANDEX_GEOCODER_KEY') ? (string) constant('THEOBROMA_YANDEX_GEOCODER_KEY') : (string) ($settings['yandex_geocoder_key'] ?? '');
                        $coordinates = (new YandexGeocoder())->coordinates($this->address($destination), $geocoderKey);
                        $latitude = $coordinates['latitude'];
                        $longitude = $coordinates['longitude'];
                    }
                    $delivery = ['courier' => ['coordinates' => ['latitude' => (float) $latitude, 'longitude' => (float) $longitude]]];
                }
                $buyer = $this->person($request);
                $quote = $ozon->quote($buyer, $delivery, $items, $buyer);
            }

            $selection = DeliverySelection::fromArray([
                'provider' => $provider,
                'kind' => $kind,
                'fingerprint' => $fingerprint,
                'point' => $point,
                'quote' => ['cost' => $quote->cost(), 'label' => $quote->label()],
                'create_payload' => $quote->createPayload(),
            ]);
            (new DeliverySelectionStore())->save($selection);
            (new ShippingRateCache())->invalidate();
            return rest_ensure_response([
                'provider' => $provider,
                'kind' => $kind,
                'point' => $point,
                'quote' => ['cost' => $quote->cost(), 'label' => $quote->label()],
            ]);
        } catch (\Throwable $exception) {
            $failure = DeliveryProviderFailure::forQuote(
                sanitize_key((string) $request->get_param('provider')),
                $exception
            );
            wc_get_logger()->error('Delivery quote unavailable', $failure->logContext());
            return new \WP_Error('delivery_quote_unavailable', $failure->publicMessage(), ['status' => 422]);
        }
    }

    public function selection(): \WP_REST_Response
    {
        $selection = (new DeliverySelectionStore())->load();
        $data = $selection?->toArray();
        if (is_array($data)) {
            unset($data['create_payload']);
        }
        return rest_ensure_response(['selection' => $data]);
    }

    public function clear(): \WP_REST_Response
    {
        (new DeliverySelectionStore())->clear();
        return rest_ensure_response(['cleared' => true]);
    }

    /** @param array<string,mixed> $settings */
    private function cdek(array $settings): CdekCheckoutService
    {
        if (($settings['cdek_enabled'] ?? 'no') !== 'yes') {
            throw new \RuntimeException('СДЭК не включён.');
        }
        $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET') ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET') : (string) ($settings['cdek_client_secret'] ?? '');
        return new CdekCheckoutService(new CdekClient(new WpTransport(), new CdekTokenStore(), (string) ($settings['cdek_client_id'] ?? ''), $secret));
    }

    /** @param array<string,mixed> $settings */
    private function ozon(array $settings): OzonCheckoutService
    {
        return new OzonCheckoutService((new OzonClientFactory(new WpTransport(), new OzonTokenStore()))->clientFromSettings($settings));
    }

    /** @param list<array<string,mixed>> $points @return array<string,mixed> */
    private function findPoint(array $points, string $id): array
    {
        foreach ($points as $point) {
            if (hash_equals((string) ($point['id'] ?? ''), sanitize_text_field($id))) {
                return $point;
            }
        }
        throw new \InvalidArgumentException('Выбранный пункт выдачи не найден.');
    }

    /** @param array<string,mixed> $fallback @return array<string,mixed> */
    private function destination(\WP_REST_Request $request, array $fallback): array
    {
        foreach (['country', 'state', 'city', 'postcode', 'address', 'address_2'] as $key) {
            $value = $request->get_param($key);
            if (is_string($value) && trim($value) !== '') {
                $fallback[$key] = sanitize_text_field($value);
            }
        }
        return $fallback;
    }

    /** @return array{first_name:string,last_name:string,middle_name:string,phone:string} */
    private function person(\WP_REST_Request $request): array
    {
        return [
            'first_name' => sanitize_text_field((string) $request->get_param('first_name')),
            'last_name' => sanitize_text_field((string) $request->get_param('last_name')),
            'middle_name' => sanitize_text_field((string) $request->get_param('middle_name')),
            'phone' => sanitize_text_field((string) $request->get_param('phone')),
        ];
    }

    /** @param array<string,mixed> $destination */
    private function address(array $destination): string
    {
        return trim(implode(', ', array_filter([
            (string) ($destination['postcode'] ?? ''),
            (string) ($destination['city'] ?? ''),
            (string) ($destination['address'] ?? ''),
            (string) ($destination['address_2'] ?? ''),
        ])));
    }

    /** @return array<string,array{lat:float,long:float}> */
    private function viewport(\WP_REST_Request $request): array
    {
        $values = [
            'left_bottom' => ['lat' => $request->get_param('left_bottom_lat'), 'long' => $request->get_param('left_bottom_long')],
            'right_top' => ['lat' => $request->get_param('right_top_lat'), 'long' => $request->get_param('right_top_long')],
        ];
        foreach ($values as $point) {
            if (!is_numeric($point['lat']) || !is_numeric($point['long'])) {
                return [];
            }
        }
        return [
            'left_bottom' => ['lat' => (float) $values['left_bottom']['lat'], 'long' => (float) $values['left_bottom']['long']],
            'right_top' => ['lat' => (float) $values['right_top']['lat'], 'long' => (float) $values['right_top']['long']],
        ];
    }
}

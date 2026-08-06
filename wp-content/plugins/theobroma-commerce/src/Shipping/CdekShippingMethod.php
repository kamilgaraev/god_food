<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore;

final class CdekShippingMethod extends \WC_Shipping_Method
{
    public function __construct(int $instanceId = 0)
    {
        $this->id = 'theobroma_cdek';
        $this->instance_id = absint($instanceId);
        $this->method_title = __('СДЭК', 'theobroma-commerce');
        $this->method_description = __('Расчёт доставки, ПВЗ и создание отправлений через CDEK API v2.', 'theobroma-commerce');
        $this->supports = ['shipping-zones', 'instance-settings'];
        $this->enabled = 'yes';
        $this->title = __('СДЭК', 'theobroma-commerce');
    }

    /** @param array<mixed> $package */
    public function calculate_shipping($package = []): void
    {
        $settings = get_option('theobroma_commerce_settings', []);
        if (($settings['cdek_enabled'] ?? 'no') !== 'yes') {
            return;
        }

        $clientId = (string) ($settings['cdek_client_id'] ?? '');
        $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET')
            ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET')
            : (string) ($settings['cdek_client_secret'] ?? '');
        $senderCode = (int) ($settings['cdek_sender_city_code'] ?? 0);
        if ($clientId === '' || $secret === '' || $senderCode <= 0) {
            return;
        }

        try {
            $lines = $this->productLines($package['contents'] ?? []);
            $destination = $package['destination'] ?? [];
            $payload = (new CdekPackageBuilder($senderCode))->build([
                'postal_code' => (string) ($destination['postcode'] ?? ''),
                'city' => (string) ($destination['city'] ?? ''),
                'address' => trim((string) ($destination['address'] ?? '') . ' ' . (string) ($destination['address_2'] ?? '')),
            ], $lines);

            $cacheKey = 'theobroma_cdek_rates_' . md5((string) wp_json_encode($payload));
            $rates = get_transient($cacheKey);
            if (!is_array($rates)) {
                $rates = (new CdekClient(new WpTransport(), new WordPressTokenStore(), $clientId, $secret))->calculateTariffs($payload);
                set_transient($cacheKey, $rates, 5 * MINUTE_IN_SECONDS);
            }

            $selector = new CdekRateSelector();
            foreach (['pickup' => __('СДЭК — пункт выдачи', 'theobroma-commerce'), 'courier' => __('СДЭК — курьер', 'theobroma-commerce')] as $kind => $label) {
                $rate = $selector->cheapest($rates, $kind);
                if ($rate === null) {
                    continue;
                }
                $days = $this->deliveryPeriod($rate);
                $this->add_rate([
                    'id' => $this->get_rate_id($kind),
                    'label' => $label . ($days !== '' ? ', ' . $days : ''),
                    'cost' => (float) $rate['delivery_sum'],
                    'meta_data' => [
                        'theobroma_provider' => 'cdek',
                        'theobroma_delivery_kind' => $kind,
                        'theobroma_tariff_code' => (int) ($rate['tariff_code'] ?? 0),
                    ],
                    'package' => $package,
                ]);
            }
        } catch (\Throwable $exception) {
            wc_get_logger()->error($exception->getMessage(), ['source' => 'theobroma-cdek']);
        }
    }

    /** @param array<mixed> $contents
     *  @return list<array{quantity:int,weight_kg:float,length_cm:float,width_cm:float,height_cm:float}>
     */
    private function productLines(array $contents): array
    {
        $lines = [];
        foreach ($contents as $item) {
            $product = $item['data'] ?? null;
            if (!$product instanceof \WC_Product || !$product->needs_shipping()) {
                continue;
            }
            $lines[] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'weight_kg' => (float) wc_get_weight((float) $product->get_weight(), 'kg'),
                'length_cm' => (float) wc_get_dimension((float) $product->get_length(), 'cm'),
                'width_cm' => (float) wc_get_dimension((float) $product->get_width(), 'cm'),
                'height_cm' => (float) wc_get_dimension((float) $product->get_height(), 'cm'),
            ];
        }
        return $lines;
    }

    /** @param array<mixed> $rate */
    private function deliveryPeriod(array $rate): string
    {
        $min = (int) ($rate['period_min'] ?? 0);
        $max = (int) ($rate['period_max'] ?? 0);
        if ($min <= 0 && $max <= 0) {
            return '';
        }
        return sprintf(_n('%s день', '%s дней', max($min, $max), 'theobroma-commerce'), $min === $max ? (string) $max : $min . '–' . $max);
    }
}

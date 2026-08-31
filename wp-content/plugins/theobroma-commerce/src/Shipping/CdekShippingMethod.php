<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

use Theobroma\Commerce\Checkout\DeliveryRateResolver;
use Theobroma\Commerce\Checkout\DeliveryRuntime;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;

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
        $quote = (new DeliveryRateResolver(new DeliverySelectionStore()))->resolve('cdek', DeliveryRuntime::fingerprint((array) $package));
        $this->add_rate([
            'id' => $this->get_rate_id((string) $quote['kind']),
            'label' => sanitize_text_field((string) $quote['label']),
            'cost' => max(0.0, (float) $quote['cost']),
            'meta_data' => (array) $quote['meta_data'],
            'package' => $package,
        ]);
    }

}

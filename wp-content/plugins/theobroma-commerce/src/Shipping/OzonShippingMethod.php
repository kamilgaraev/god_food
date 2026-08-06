<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

use Theobroma\Commerce\Integrations\Ozon\OzonReadinessFactory;

final class OzonShippingMethod extends \WC_Shipping_Method
{
    public function __construct(int $instanceId = 0)
    {
        $this->id = 'theobroma_ozon';
        $this->instance_id = absint($instanceId);
        $this->method_title = __('Ozon Доставка', 'theobroma-commerce');
        $this->method_description = __('Доставка через приватное приложение Ozon после допуска, маппинга каталога и успешного live-теста.', 'theobroma-commerce');
        $this->supports = ['shipping-zones', 'instance-settings'];
        $this->enabled = 'yes';
        $this->title = __('Ozon Доставка', 'theobroma-commerce');
    }

    /** @param array<mixed> $package */
    public function calculate_shipping($package = []): void
    {
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $token = defined('THEOBROMA_OZON_ACCESS_TOKEN')
            ? (string) constant('THEOBROMA_OZON_ACCESS_TOKEN')
            : (string) ($settings['ozon_access_token'] ?? '');
        $products = wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'objects']);
        $capabilities = (new OzonReadinessFactory())->build($settings, $token !== '', $products);

        if (($settings['ozon_enabled'] ?? 'no') !== 'yes' || !$capabilities->canOfferDelivery()) {
            return;
        }

        // A rate may only come from a confirmed live Ozon checkout response. Until the
        // merchant flow supplies that response, fail closed instead of inventing a tariff.
        $quote = apply_filters('theobroma_ozon_confirmed_quote', null, $package, $this);
        if (!is_array($quote) || !isset($quote['cost'], $quote['label'], $quote['create_payload'])) {
            return;
        }

        $this->add_rate([
            'id' => $this->get_rate_id('delivery'),
            'label' => sanitize_text_field((string) $quote['label']),
            'cost' => max(0.0, (float) $quote['cost']),
            'meta_data' => [
                'theobroma_provider' => 'ozon',
                'theobroma_ozon_create_payload' => wp_json_encode((array) $quote['create_payload']),
            ],
            'package' => $package,
        ]);
    }
}

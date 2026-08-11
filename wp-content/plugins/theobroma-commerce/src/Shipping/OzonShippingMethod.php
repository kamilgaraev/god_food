<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Support\ProviderException;

final class OzonShippingMethod extends \WC_Shipping_Method
{
    public function __construct(int $instanceId = 0)
    {
        $this->id = 'theobroma_ozon';
        $this->instance_id = absint($instanceId);
        $this->method_title = __('Ozon Доставка', 'theobroma-commerce');
        $this->method_description = __('Доставка через частное приложение Ozon для корзин, где у всех товаров заполнен Ozon SKU.', 'theobroma-commerce');
        $this->supports = ['shipping-zones', 'instance-settings'];
        $this->enabled = 'yes';
        $this->title = __('Ozon Доставка', 'theobroma-commerce');
    }

    /** @param array<mixed> $package */
    public function calculate_shipping($package = []): void
    {
        $settings = (array) get_option('theobroma_commerce_settings', []);
        if (!(new OzonCartEligibility())->allItemsMapped(is_array($package) ? $package : [])) {
            return;
        }

        try {
            $transport = new WpTransport();
            $factory = new OzonClientFactory($transport, new WordPressTokenStore());
            $authenticator = $factory->authenticatorFromSettings($settings);
            $authenticator->token();
            $client = new OzonClient($transport, $authenticator);
            $quote = apply_filters('theobroma_ozon_confirmed_quote', null, $package, $this, $client);
        } catch (\Throwable $exception) {
            wc_get_logger()->error('Ozon shipping quote unavailable', [
                'source' => 'theobroma-ozon',
                'status' => $exception instanceof ProviderException ? $exception->statusCode() : 0,
            ]);
            return;
        }
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

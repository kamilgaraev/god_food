<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Support\ProviderException;
use Theobroma\Commerce\Checkout\DeliveryRateResolver;
use Theobroma\Commerce\Checkout\DeliveryRuntime;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;

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
        $cart = function_exists('WC') ? WC()->cart : null;
        $contents = is_object($cart) && method_exists($cart, 'get_cart')
            ? $cart->get_cart()
            : (is_array($package) && is_array($package['contents'] ?? null) ? $package['contents'] : []);
        if (!(new OzonCartEligibility())->allContentsMapped(is_array($contents) ? $contents : [])) {
            return;
        }

        try {
            $transport = new WpTransport();
            $factory = new OzonClientFactory($transport, new WordPressTokenStore());
            $authenticator = $factory->authenticatorFromSettings($settings);
            $authenticator->token();
        } catch (\Throwable $exception) {
            wc_get_logger()->error('Ozon shipping quote unavailable', [
                'source' => 'theobroma-ozon',
                'status' => $exception instanceof ProviderException ? $exception->statusCode() : 0,
            ]);
            return;
        }
        $quote = (new DeliveryRateResolver(new DeliverySelectionStore()))->resolve('ozon', DeliveryRuntime::fingerprint((array) $package));

        $this->add_rate([
            'id' => $this->get_rate_id((string) $quote['kind']),
            'label' => sanitize_text_field((string) $quote['label']),
            'cost' => max(0.0, (float) $quote['cost']),
            'meta_data' => (array) $quote['meta_data'],
            'package' => $package,
        ]);
    }
}

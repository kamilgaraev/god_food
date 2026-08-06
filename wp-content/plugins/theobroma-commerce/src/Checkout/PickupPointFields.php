<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class PickupPointFields
{
    public function register(): void
    {
        add_action('woocommerce_after_shipping_rate', [$this, 'field'], 20, 2);
        add_action('woocommerce_checkout_process', [$this, 'validate']);
        add_action('woocommerce_checkout_create_order', [$this, 'save'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function field(\WC_Shipping_Rate $rate, int $index): void
    {
        if (!str_contains($rate->get_id(), 'theobroma_cdek') || !str_contains($rate->get_id(), 'pickup')) {
            return;
        }
        echo '<div class="theobroma-pickup-field"><label>' . esc_html__('Пункт выдачи СДЭК', 'theobroma-commerce') . '<select name="theobroma_cdek_point" data-cdek-points><option value="">' . esc_html__('Выберите ПВЗ', 'theobroma-commerce') . '</option></select></label></div>';
    }

    public function validate(): void
    {
        $methods = array_map('sanitize_text_field', (array) wp_unslash($_POST['shipping_method'] ?? []));
        if ($this->hasPickup($methods) && sanitize_text_field(wp_unslash($_POST['theobroma_cdek_point'] ?? '')) === '') {
            wc_add_notice(__('Выберите пункт выдачи СДЭК.', 'theobroma-commerce'), 'error');
        }
    }

    public function save(\WC_Order $order, array $data): void
    {
        $point = sanitize_text_field(wp_unslash($_POST['theobroma_cdek_point'] ?? ''));
        if ($point !== '') {
            $order->update_meta_data('_theobroma_cdek_point', $point);
        }
    }

    public function assets(): void
    {
        if (!is_checkout() && !is_cart()) {
            return;
        }
        wp_enqueue_script('theobroma-commerce-checkout', THEOBROMA_COMMERCE_URL . 'assets/js/checkout.js', ['jquery'], '0.1.0', true);
        wp_localize_script('theobroma-commerce-checkout', 'theobromaDelivery', ['pointsUrl' => rest_url('theobroma-commerce/v1/cdek/points')]);
    }

    /** @param list<string> $methods */
    private function hasPickup(array $methods): bool
    {
        foreach ($methods as $method) {
            if (str_contains($method, 'theobroma_cdek') && str_contains($method, 'pickup')) {
                return true;
            }
        }
        return false;
    }
}

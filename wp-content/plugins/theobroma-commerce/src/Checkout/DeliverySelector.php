<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

use Theobroma\Commerce\Rest\DeliveryCheckoutController;

final class DeliverySelector
{
    private bool $rendered = false;

    public function register(): void
    {
        add_action('woocommerce_after_shipping_rate', [$this, 'button'], 20, 2);
        add_action('woocommerce_after_checkout_form', [$this, 'dialog']);
        add_action('woocommerce_after_cart', [$this, 'dialog']);
        add_action('woocommerce_checkout_process', [$this, 'validate']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function button(\WC_Shipping_Rate $rate, int $index): void
    {
        $provider = $this->provider($rate->get_id());
        if ($provider === '') {
            return;
        }
        $meta = $rate->get_meta_data();
        $requiresSelection = ($meta['theobroma_requires_selection'] ?? '') === 'yes';
        $label = $requiresSelection ? __('Выбрать доставку', 'theobroma-commerce') : __('Изменить', 'theobroma-commerce');
        printf(
            '<button type="button" class="theobroma-delivery-open" data-delivery-open="%s">%s</button>',
            esc_attr($provider),
            esc_html($label)
        );
    }

    public function dialog(): void
    {
        if ($this->rendered) {
            return;
        }
        $this->rendered = true;
        ?>
        <dialog class="theobroma-delivery-dialog" data-delivery-dialog aria-labelledby="theobroma-delivery-title">
            <form method="dialog" class="theobroma-delivery-shell">
                <header class="theobroma-delivery-header">
                    <div>
                        <p class="theobroma-delivery-eyebrow" data-delivery-provider>Доставка</p>
                        <h2 id="theobroma-delivery-title">Как доставить заказ?</h2>
                    </div>
                    <button class="theobroma-delivery-close" value="cancel" aria-label="Закрыть">×</button>
                </header>

                <div class="theobroma-delivery-tabs" role="tablist" aria-label="Способ доставки">
                    <button type="button" role="tab" aria-selected="true" data-delivery-kind="pickup">В пункт выдачи</button>
                    <button type="button" role="tab" aria-selected="false" data-delivery-kind="courier">Курьером</button>
                </div>

                <section data-delivery-pickup>
                    <label class="theobroma-delivery-search">
                        <span>Найти пункт по адресу</span>
                        <input type="search" data-delivery-search placeholder="Улица, метро или район" autocomplete="off">
                    </label>
                    <div class="theobroma-delivery-grid">
                        <div class="theobroma-delivery-list" data-delivery-list aria-live="polite"></div>
                        <div class="theobroma-delivery-map" data-delivery-map hidden aria-label="Карта пунктов выдачи"></div>
                    </div>
                </section>

                <section class="theobroma-delivery-courier" data-delivery-courier hidden>
                    <div class="theobroma-delivery-fields">
                        <label><span>Город</span><input data-delivery-field="city" autocomplete="address-level2" required></label>
                        <label><span>Индекс</span><input data-delivery-field="postcode" autocomplete="postal-code"></label>
                        <label class="wide"><span>Улица, дом, квартира</span><input data-delivery-field="address" autocomplete="address-line1" required></label>
                        <label class="wide"><span>Подъезд, этаж, комментарий</span><input data-delivery-field="address_2" autocomplete="address-line2"></label>
                    </div>
                </section>

                <p class="theobroma-delivery-status" data-delivery-status aria-live="polite"></p>
                <footer class="theobroma-delivery-footer">
                    <button type="button" class="button alt" data-delivery-confirm>Рассчитать и выбрать</button>
                </footer>
            </form>
        </dialog>
        <?php
    }

    public function validate(): void
    {
        $methods = array_map('sanitize_text_field', (array) wp_unslash($_POST['shipping_method'] ?? []));
        foreach ($methods as $method) {
            $provider = $this->provider($method);
            if ($provider === '') {
                continue;
            }
            $package = DeliveryRuntime::currentPackage();
            if (!(new DeliverySelectionStore())->confirmedFor($provider, DeliveryRuntime::fingerprint($package))) {
                wc_add_notice(__('Выберите способ доставки и подтвердите расчёт.', 'theobroma-commerce'), 'error');
            }
        }
    }

    public function assets(): void
    {
        if (!is_checkout() && !is_cart()) {
            return;
        }
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $mapKey = defined('THEOBROMA_YANDEX_MAPS_JS_KEY')
            ? (string) constant('THEOBROMA_YANDEX_MAPS_JS_KEY')
            : (string) ($settings['yandex_maps_js_key'] ?? '');

        wp_enqueue_style('theobroma-commerce-delivery', THEOBROMA_COMMERCE_URL . 'assets/css/checkout-delivery.css', [], '0.2.1');
        wp_enqueue_script('theobroma-delivery-core', THEOBROMA_COMMERCE_URL . 'assets/js/delivery-selector-core.js', [], '0.2.0', true);
        wp_enqueue_script('theobroma-commerce-checkout', THEOBROMA_COMMERCE_URL . 'assets/js/checkout.js', ['jquery', 'theobroma-delivery-core'], '0.2.0', true);
        wp_localize_script('theobroma-commerce-checkout', 'theobromaDelivery', [
            'pointsUrl' => rest_url('theobroma-commerce/v1/delivery/points'),
            'quoteUrl' => rest_url('theobroma-commerce/v1/delivery/quote'),
            'selectionUrl' => rest_url('theobroma-commerce/v1/delivery/selection'),
            'nonce' => wp_create_nonce(DeliveryCheckoutController::NONCE_ACTION),
            'mapEnabled' => $mapKey !== '',
            'mapKey' => $mapKey,
        ]);
        if ($mapKey !== '') {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=' . rawurlencode($mapKey), [], null, true);
        }
    }

    private function provider(string $method): string
    {
        if (str_contains($method, 'theobroma_ozon')) {
            return 'ozon';
        }
        return str_contains($method, 'theobroma_cdek') ? 'cdek' : '';
    }
}

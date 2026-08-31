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
            <div class="theobroma-delivery-shell">
                <header class="theobroma-delivery-header">
                    <div>
                        <p class="theobroma-delivery-eyebrow" data-delivery-provider>Доставка</p>
                        <h2 id="theobroma-delivery-title">Как доставить заказ?</h2>
                    </div>
                    <button type="button" class="theobroma-delivery-close" data-delivery-close aria-label="Закрыть"><span aria-hidden="true"></span></button>
                </header>

                <div class="theobroma-delivery-tabs" role="tablist" aria-label="Способ доставки">
                    <button type="button" role="tab" aria-selected="true" data-delivery-kind="pickup">В пункт выдачи</button>
                    <button type="button" role="tab" aria-selected="false" data-delivery-kind="courier">Курьером</button>
                </div>

                <section data-delivery-pickup>
                    <div class="theobroma-delivery-search">
                        <label for="theobroma-delivery-search">Найти пункт по адресу</label>
                        <span class="theobroma-delivery-search-control">
                            <input id="theobroma-delivery-search" type="text" data-delivery-search placeholder="Город, улица или дом" autocomplete="off" aria-autocomplete="list" aria-controls="theobroma-delivery-suggestions">
                            <button type="button" class="theobroma-delivery-search-clear" data-delivery-search-clear aria-label="Очистить поиск" hidden><span aria-hidden="true"></span></button>
                        </span>
                        <span class="theobroma-delivery-suggestions" id="theobroma-delivery-suggestions" data-delivery-suggestions role="listbox" hidden></span>
                    </div>
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
            </div>
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
        if (!$this->shouldLoadAssets(is_admin())) {
            return;
        }
        $settings = (array) get_option('theobroma_commerce_settings', []);
        $mapKey = defined('THEOBROMA_YANDEX_MAPS_JS_KEY')
            ? (string) constant('THEOBROMA_YANDEX_MAPS_JS_KEY')
            : (string) ($settings['yandex_maps_js_key'] ?? '');

        wp_enqueue_style('theobroma-commerce-delivery', THEOBROMA_COMMERCE_URL . 'assets/css/checkout-delivery.css', [], '0.2.4');
        wp_enqueue_script('theobroma-delivery-core', THEOBROMA_COMMERCE_URL . 'assets/js/delivery-selector-core.js', [], '0.2.2', true);
        wp_enqueue_script('theobroma-commerce-checkout', THEOBROMA_COMMERCE_URL . 'assets/js/checkout.js', ['jquery', 'theobroma-delivery-core'], '0.2.4', true);
        wp_localize_script('theobroma-commerce-checkout', 'theobromaDelivery', [
            'pointsUrl' => rest_url('theobroma-commerce/v1/delivery/points'),
            'suggestionsUrl' => rest_url('theobroma-commerce/v1/delivery/suggestions'),
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

    public function shouldLoadAssets(bool $admin): bool
    {
        return !$admin;
    }

    private function provider(string $method): string
    {
        if (str_contains($method, 'theobroma_ozon')) {
            return 'ozon';
        }
        return str_contains($method, 'theobroma_cdek') ? 'cdek' : '';
    }
}

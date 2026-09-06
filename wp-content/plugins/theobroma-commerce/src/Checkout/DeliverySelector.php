<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

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
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'rateLabel'], 20, 2);
    }

    public function button(\WC_Shipping_Rate $rate, int $index): void
    {
        $provider = $this->provider($rate->get_id());
        if ($provider === '') {
            return;
        }
        $meta = $rate->get_meta_data();
        $requiresSelection = ($meta['theobroma_requires_selection'] ?? '') === 'yes';
        $label = $requiresSelection ? __('Выбрать доставку', 'theobroma-commerce') : __('Изменить доставку', 'theobroma-commerce');
        printf(
            '<button type="button" class="theobroma-delivery-open%s" data-delivery-open="%s">%s</button>',
            $requiresSelection ? '' : ' is-confirmed',
            esc_attr($provider),
            esc_html($label)
        );
        if (!$requiresSelection) {
            $kind = (string) ($meta['theobroma_delivery_kind'] ?? '');
            $address = trim((string) ($meta['theobroma_pickup_address'] ?? ''));
            $kindLabel = $kind === 'pickup'
                ? __('Пункт выдачи', 'theobroma-commerce')
                : __('Курьерская доставка', 'theobroma-commerce');
            printf(
                '<div class="theobroma-delivery-selection" role="status"><span class="theobroma-delivery-selection-check" aria-hidden="true">&#10003;</span><span class="theobroma-delivery-selection-copy"><strong>%s</strong><span>%s</span>%s</span></div>',
                esc_html(__('Доставка выбрана', 'theobroma-commerce')),
                esc_html($kindLabel),
                $address !== '' ? '<small>' . esc_html($address) . '</small>' : ''
            );
        }
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
              <div class="theobroma-delivery-content">
                <header class="theobroma-delivery-header">
                    <div>
                        <p class="theobroma-delivery-eyebrow" data-delivery-provider>Доставка</p>
                        <h2 id="theobroma-delivery-title">Как доставить заказ?</h2>
                    </div>
                    <button type="button" class="theobroma-delivery-close" data-delivery-close aria-label="Закрыть"><span aria-hidden="true"></span></button>
                </header>

                <div class="theobroma-delivery-fields theobroma-delivery-destination">
                    <label><span>Страна</span><select data-delivery-field="country" autocomplete="country"><?php foreach (array_intersect_key(WC()->countries->get_shipping_countries(), WC()->countries->get_allowed_countries()) as $code => $name) : ?><option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
                    <label><span>Город</span><input data-delivery-field="city" autocomplete="address-level2" placeholder="Например, Алматы" required></label>
                </div>
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
                        <label><span>Индекс</span><input data-delivery-field="postcode" autocomplete="postal-code"></label>
                        <label class="wide"><span>Улица, дом, квартира</span><input data-delivery-field="address" autocomplete="address-line1" list="theobroma-courier-suggestions" required><datalist id="theobroma-courier-suggestions"></datalist></label>
                        <label class="wide"><span>Подъезд, этаж, комментарий</span><input data-delivery-field="address_2" autocomplete="address-line2"></label>
                    </div>
                </section>

              </div>
                <footer class="theobroma-delivery-footer">
                    <p class="theobroma-delivery-status" data-delivery-status aria-live="polite"></p>
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
        $suggestKey = defined('THEOBROMA_YANDEX_SUGGEST_KEY')
            ? (string) constant('THEOBROMA_YANDEX_SUGGEST_KEY')
            : (string) ($settings['yandex_suggest_key'] ?? '');

        $osm = ($settings['map_provider'] ?? 'yandex') === 'osm';
        if ($osm) {
            wp_enqueue_style('theobroma-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            wp_enqueue_script('theobroma-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
        }
        wp_enqueue_style('theobroma-commerce-delivery', THEOBROMA_COMMERCE_URL . 'assets/css/checkout-delivery.css', [], '0.4.12');
        wp_enqueue_script('theobroma-delivery-core', THEOBROMA_COMMERCE_URL . 'assets/js/delivery-selector-core.js', [], '0.2.2', true);
        wp_enqueue_script('theobroma-commerce-checkout', THEOBROMA_COMMERCE_URL . 'assets/js/checkout.js', $osm ? ['jquery', 'theobroma-delivery-core', 'theobroma-leaflet'] : ['jquery', 'theobroma-delivery-core'], '0.5.3', true);
        $officialCdek = class_exists('\\Cdek\\ShippingMethod');
        if ($officialCdek && class_exists('\\Cdek\\Helpers\\UI')) {
            \Cdek\Helpers\UI::enqueueScript('cdek-map', 'cdek-checkout-map', true);
            wp_add_inline_style('theobroma-commerce-delivery', '.commerce-cart-checkout #billing_country_field,.commerce-cart-checkout #billing_city_field{display:block!important}.commerce-cart-checkout .open-pvz-btn{width:100%;padding:12px 0}.commerce-cart-checkout .open-pvz-btn a{display:inline-block;background:#714727;color:#fff;padding:12px 18px;border-radius:10px;cursor:pointer}');
        }
        wp_localize_script('theobroma-commerce-checkout', 'theobromaDelivery', [
            'officialCdek' => $officialCdek,
            'pointsUrl' => rest_url('theobroma-commerce/v1/delivery/points'),
            'suggestionsUrl' => rest_url('theobroma-commerce/v1/delivery/suggestions'),
            'quoteUrl' => $this->quoteUrl(),
            'selectionUrl' => rest_url('theobroma-commerce/v1/delivery/selection'),
            'mapProvider' => $osm ? 'osm' : 'yandex',
            'mapEnabled' => $osm || $mapKey !== '',
            'mapKey' => $mapKey,
            'suggestEnabled' => !$osm && $mapKey !== '' && $suggestKey !== '',
        ]);
        if (!$osm && $mapKey !== '') {
            wp_enqueue_script('yandex-maps', $this->mapScriptUrl($mapKey, $suggestKey), [], null, true);
        }
    }

    public function shouldLoadAssets(bool $admin): bool
    {
        return !$admin;
    }

    public function quoteUrl(): string
    {
        return class_exists('WC_AJAX')
            ? \WC_AJAX::get_endpoint('theobroma_delivery_quote')
            : rest_url('theobroma-commerce/v1/delivery/quote');
    }

    public function mapScriptUrl(string $mapKey, string $suggestKey): string
    {
        $url = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=' . rawurlencode(trim($mapKey));
        if (trim($suggestKey) !== '') {
            $url .= '&suggest_apikey=' . rawurlencode(trim($suggestKey));
        }
        return $url;
    }

    public function rateLabel(string $label, \WC_Shipping_Rate $rate): string
    {
        return $this->bootstrapRateLabel($rate->get_id(), $rate->get_meta_data(), $label);
    }

    /** @param array<string,mixed> $meta */
    public function bootstrapRateLabel(string $rateId, array $meta, string $fallback): string
    {
        if (($meta['theobroma_requires_selection'] ?? '') !== 'yes') {
            return $fallback;
        }
        $provider = $this->provider($rateId);
        if ($provider === 'ozon') {
            return 'Ozon Доставка';
        }
        return $provider === 'cdek' ? 'СДЭК' : $fallback;
    }

    private function provider(string $method): string
    {
        if (str_contains($method, 'theobroma_ozon')) {
            return 'ozon';
        }
        return str_contains($method, 'theobroma_cdek') ? 'cdek' : '';
    }
}

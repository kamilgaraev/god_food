<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class LoyaltyCheckout
{
    private const COUPON_CODE = 'theobroma-bonus';
    private const SESSION_KEY = 'theobroma_bonus_requested_kopecks';

    private LoyaltyStore $store;
    private LoyaltyService $service;

    public function __construct(
        ?LoyaltyStore $store = null,
        private readonly LoyaltyCalculator $calculator = new LoyaltyCalculator()
    ) {
        if (!$store instanceof LoyaltyStore) {
            global $wpdb;
            $store = new WpdbLoyaltyStore($wpdb);
        }
        $this->store = $store;
        $this->service = new LoyaltyService($store);
    }

    public function register(): void
    {
        add_action('wp_loaded', [$this, 'syncCoupon'], 30);
        // This hook is inside the payment fragment and also runs for the AJAX cart modal.
        add_action('woocommerce_checkout_before_terms_and_conditions', [$this, 'render'], 5);
        add_action('wp_ajax_theobroma_set_bonus', [$this, 'ajaxSet']);
        add_filter('woocommerce_get_shop_coupon_data', [$this, 'couponData'], 10, 2);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 30, 2);
        add_action('woocommerce_checkout_order_created', [$this, 'reserveForOrder'], 20);
        add_action('woocommerce_checkout_order_processed', [$this, 'clearAfterOrder'], 20);
        add_action('woocommerce_cart_emptied', [$this, 'clearSession']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function acceptedAmount(int $userId, int $merchandiseKopecks, int $requestedKopecks): int
    {
        if ($userId < 1 || $merchandiseKopecks < 1 || $requestedKopecks < 1) {
            return 0;
        }

        $balance = $this->store->balance($userId);
        $limit = $this->calculator->redemptionLimit(
            $merchandiseKopecks,
            (int) ($balance['available_kopecks'] ?? 0)
        );

        return min($requestedKopecks, $limit);
    }

    public function render(): void
    {
        if (!is_user_logged_in() || !WC()->session || !WC()->cart) {
            return;
        }

        $userId = get_current_user_id();
        $balance = $this->store->balance($userId);
        $available = max(0, (int) ($balance['available_kopecks'] ?? 0));
        $maximum = $this->calculator->redemptionLimit($this->currentMerchandiseKopecks(), $available);
        $requested = min($maximum, $this->sessionAmount());
        ?>
        <section class="theobroma-loyalty-checkout" aria-labelledby="theobroma-loyalty-title">
            <h3 id="theobroma-loyalty-title">Использовать бонусы</h3>
            <p class="theobroma-loyalty-balance">
                Доступно: <strong><?php echo wp_kses_post(wc_price($available / 100)); ?></strong>.
                Можно списать до <strong><?php echo wp_kses_post(wc_price($maximum / 100)); ?></strong>.
            </p>
            <div class="theobroma-loyalty-control">
                <label for="theobroma_bonus_amount">Сколько бонусов списать</label>
                <input
                    id="theobroma_bonus_amount"
                    name="theobroma_bonus_amount"
                    type="number"
                    min="0"
                    max="<?php echo esc_attr(number_format($maximum / 100, 2, '.', '')); ?>"
                    step="0.01"
                    value="<?php echo esc_attr(number_format($requested / 100, 2, '.', '')); ?>"
                    inputmode="decimal"
                >
                <button type="button" class="button" data-theobroma-bonus-apply>Применить</button>
            </div>
            <p class="theobroma-loyalty-status" aria-live="polite"></p>
        </section>
        <?php
    }

    public function ajaxSet(): void
    {
        check_ajax_referer('theobroma_set_bonus', 'nonce');
        if (!is_user_logged_in() || !WC()->session || !WC()->cart) {
            wp_send_json_error(['message' => 'Бонусы доступны после входа в личный кабинет.'], 403);
        }

        $raw = isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : '0';
        $requested = max(0, (int) round((float) wc_format_decimal($raw) * 100));
        $accepted = $this->acceptedAmount(get_current_user_id(), $this->currentMerchandiseKopecks(), $requested);
        WC()->session->set(self::SESSION_KEY, $accepted);
        $this->syncCoupon();
        WC()->cart->calculate_totals();

        wp_send_json_success([
            'accepted_kopecks' => $accepted,
            'accepted' => number_format($accepted / 100, 2, '.', ''),
            'message' => $accepted > 0 ? 'Бонусы применены.' : 'Списание бонусов отключено.',
        ]);
    }

    public function couponData(mixed $couponData, string $couponCode): mixed
    {
        if (wc_format_coupon_code($couponCode) !== self::COUPON_CODE || !is_user_logged_in()) {
            return $couponData;
        }

        $amount = $this->sessionAmount();
        if ($amount < 1) {
            return $couponData;
        }

        return [
            'amount' => number_format($amount / 100, 2, '.', ''),
            'discount_type' => 'fixed_cart',
            'individual_use' => false,
            'product_ids' => [],
            'exclude_product_ids' => [],
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'limit_usage_to_x_items' => null,
            'free_shipping' => false,
            'description' => 'Списание бонусов Theobroma',
        ];
    }

    public function validate(array $data, \WP_Error $errors): void
    {
        if (!is_user_logged_in() || $this->sessionAmount() < 1) {
            return;
        }

        $requested = $this->sessionAmount();
        $accepted = $this->acceptedAmount(get_current_user_id(), $this->currentMerchandiseKopecks(), $requested);
        if ($accepted !== $requested) {
            $errors->add(
                'theobroma_bonus_changed',
                'Баланс или состав корзины изменился. Примените бонусы ещё раз и проверьте итог заказа.'
            );
        }
    }

    public function reserveForOrder(\WC_Order $order): void
    {
        $userId = (int) $order->get_customer_id();
        $requested = $this->sessionAmount();
        if ($userId < 1 || $requested < 1) {
            return;
        }

        $accepted = $this->acceptedAmount($userId, $this->currentMerchandiseKopecks(), $requested);
        if ($accepted !== $requested) {
            throw new \RuntimeException('Баланс бонусов изменился. Обновите страницу оформления заказа.');
        }

        $this->service->reserve($userId, (int) $order->get_id(), $accepted);
        $order->update_meta_data('_theobroma_bonus_reserved_kopecks', $accepted);
        $order->save();
    }

    public function clearAfterOrder(int $orderId): void
    {
        $this->clearSession();
    }

    public function clearSession(): void
    {
        if (WC()->session) {
            WC()->session->set(self::SESSION_KEY, null);
        }
        if (WC()->cart && WC()->cart->has_discount(self::COUPON_CODE)) {
            WC()->cart->remove_coupon(self::COUPON_CODE);
        }
    }

    public function syncCoupon(): void
    {
        if (!function_exists('WC') || !WC()->cart || !WC()->session) {
            return;
        }

        $shouldApply = is_user_logged_in() && $this->sessionAmount() > 0;
        $isApplied = WC()->cart->has_discount(self::COUPON_CODE);
        if ($shouldApply && !$isApplied) {
            WC()->cart->apply_coupon(self::COUPON_CODE);
        } elseif (!$shouldApply && $isApplied) {
            WC()->cart->remove_coupon(self::COUPON_CODE);
        }
    }

    public function assets(): void
    {
        // Authenticated customers can open the AJAX checkout modal from any storefront page.
        if (!is_user_logged_in()) {
            return;
        }

        $relative = 'assets/js/loyalty-checkout.js';
        $path = THEOBROMA_COMMERCE_PATH . $relative;
        wp_enqueue_script(
            'theobroma-loyalty-checkout',
            THEOBROMA_COMMERCE_URL . $relative,
            ['jquery', 'wc-checkout'],
            is_file($path) ? (string) filemtime($path) : '1.0.0',
            true
        );
        wp_localize_script('theobroma-loyalty-checkout', 'theobromaLoyalty', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('theobroma_set_bonus'),
        ]);
    }

    private function sessionAmount(): int
    {
        return WC()->session ? max(0, (int) WC()->session->get(self::SESSION_KEY, 0)) : 0;
    }

    private function currentMerchandiseKopecks(): int
    {
        if (!WC()->cart) {
            return 0;
        }

        $total = (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax();
        if (WC()->cart->has_discount(self::COUPON_CODE)) {
            $total += (float) WC()->cart->get_coupon_discount_amount(self::COUPON_CODE, false);
            $total += (float) WC()->cart->get_coupon_discount_tax_amount(self::COUPON_CODE);
        }

        return max(0, (int) round($total * 100));
    }
}

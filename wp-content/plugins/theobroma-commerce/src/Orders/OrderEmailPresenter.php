<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class OrderEmailPresenter
{
    private const DESCRIPTION_META = '_theobroma_email_description';
    private const SKU_META = '_theobroma_email_sku';

    /** @var list<object> */
    private array $emailOrders = [];

    public function register(): void
    {
        add_action('woocommerce_before_order_item_object_save', [$this, 'captureItem']);
        add_action('woocommerce_email_before_order_table', [$this, 'beginOrderTable'], 5);
        add_action('woocommerce_email_after_order_table', [$this, 'endOrderTable'], 100, 0);
        add_filter('woocommerce_email_order_items_args', [$this, 'itemArguments']);
        add_action('woocommerce_order_item_meta_end', [$this, 'renderItem'], 20, 4);
        add_action('woocommerce_email_customer_details', [$this, 'renderDelivery'], 40, 4);
    }

    public function captureItem(object $item): void
    {
        if ($item->get_id() || !method_exists($item, 'get_product')) {
            return;
        }
        $product = $item->get_product();
        if (!$product) {
            return;
        }
        if (!$item->meta_exists(self::DESCRIPTION_META)) {
            $item->add_meta_data(self::DESCRIPTION_META, $this->productDescription($product), true);
        }
        if (!$item->meta_exists(self::SKU_META)) {
            $item->add_meta_data(self::SKU_META, $this->text($product->get_sku()), true);
        }
    }

    public function beginOrderTable(object $order): void
    {
        $this->emailOrders[] = $order;
    }

    public function endOrderTable(): void
    {
        array_pop($this->emailOrders);
    }

    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function itemArguments(array $arguments): array
    {
        if ($this->isEmailOrder($arguments['order'] ?? null)) {
            // Text remains readable when the recipient blocks remote images.
            $arguments['show_image'] = false;
            $arguments['show_sku'] = false;
        }
        return $arguments;
    }

    /** @return array{description:string,sku:string} */
    public function itemDetails(object $item): array
    {
        $product = $item->get_product();
        $description = $item->meta_exists(self::DESCRIPTION_META)
            ? $this->text((string) $item->get_meta(self::DESCRIPTION_META, true))
            : ($product ? $this->productDescription($product) : '');
        $sku = $item->meta_exists(self::SKU_META)
            ? $this->text((string) $item->get_meta(self::SKU_META, true))
            : ($product ? $this->text($product->get_sku()) : '');
        if ($description !== '' && mb_stripos($this->text($item->get_name()), $description, 0, 'UTF-8') !== false) {
            $description = '';
        }
        return ['description' => $description, 'sku' => $sku];
    }

    public function renderItem(mixed $itemId, object $item, object $order, bool $plainText): void
    {
        if (!$this->isEmailOrder($order) || !method_exists($item, 'get_product')) {
            return;
        }
        $details = $this->itemDetails($item);
        $lines = array_filter([
            $details['description'],
            $details['sku'] !== '' ? 'Артикул: ' . $details['sku'] : '',
        ], static fn (string $line): bool => $line !== '');
        if ($lines === []) {
            return;
        }
        if ($plainText) {
            echo "\n" . implode("\n", $lines) . "\n";
            return;
        }
        echo '<div class="theobroma-email-item-details" style="margin-top:6px;font-size:14px;line-height:1.5;color:#343434;word-wrap:break-word;">';
        foreach ($lines as $line) {
            echo '<div>' . esc_html($line) . '</div>';
        }
        echo '</div>';
    }

    /** @return list<string> */
    public function deliveryLabels(object $order): array
    {
        $providers = ['theobroma_ozon' => 'Ozon', 'theobroma_cdek' => 'СДЭК'];
        $kinds = ['pickup' => 'пункт выдачи', 'courier' => 'курьер'];
        $labels = [];
        $shipping = $order->get_items('shipping');
        foreach ($shipping as $item) {
            $method = $item->get_method_id();
            $label = $providers[$method] ?? $this->text($item->get_name());
            if (isset($providers[$method])) {
                $kind = (string) $item->get_meta('theobroma_delivery_kind', true);
                if (isset($kinds[$kind])) {
                    $label .= ' — ' . $kinds[$kind];
                }
            }
            if ($label !== '') {
                $labels[] = $label;
            }
        }
        // Orders created before shipping-line metadata may only retain the selection on the order.
        if ($shipping === []) {
            $provider = (string) $order->get_meta('_theobroma_delivery_provider', true);
            $label = ['ozon' => 'Ozon', 'cdek' => 'СДЭК'][$provider] ?? '';
            $kind = (string) $order->get_meta('_theobroma_delivery_kind', true);
            if ($label !== '') {
                $labels[] = $label . (isset($kinds[$kind]) ? ' — ' . $kinds[$kind] : '');
            }
        }
        return array_values(array_unique($labels));
    }

    public function renderDelivery(object $order, bool $sentToAdmin, bool $plainText, mixed $email = null): void
    {
        $labels = $this->deliveryLabels($order);
        if ($labels === []) {
            return;
        }
        $text = implode('; ', $labels);
        if ($plainText) {
            echo "\nСлужба доставки: " . $text . "\n";
            return;
        }
        echo '<h2 style="margin-top:24px;">Служба доставки</h2><p style="font-size:15px;line-height:1.5;"><strong>'
            . esc_html($text) . '</strong></p>';
    }

    private function isEmailOrder(mixed $order): bool
    {
        return $order !== null && $this->emailOrders !== [] && end($this->emailOrders) === $order;
    }

    private function productDescription(object $product): string
    {
        $description = $this->text($product->get_short_description());
        if ($description === '' && $product->get_parent_id()) {
            $parent = wc_get_product($product->get_parent_id());
            $description = $parent ? $this->text($parent->get_short_description()) : '';
        }
        return $description;
    }

    private function text(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|h[1-6])>/i', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($value)) ?? '');
    }
}

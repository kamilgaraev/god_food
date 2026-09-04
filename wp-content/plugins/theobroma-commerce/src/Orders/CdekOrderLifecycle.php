<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore;

final class CdekOrderLifecycle
{
    public function register(): void
    {
        add_action('woocommerce_order_status_processing', [$this, 'createShipment'], 20);
        add_action('woocommerce_checkout_order_processed', [$this, 'createCodShipment'], 20, 3);
    }

    public function createShipment(int $orderId): void
    {
        $this->dispatch($orderId, 'processing');
    }

    /** @param array<string,mixed> $postedData */
    public function createCodShipment(int $orderId, array $postedData = [], ?\WC_Order $order = null): void
    {
        $this->dispatch($orderId, 'checkout', $order);
    }

    private function dispatch(int $orderId, string $event, ?\WC_Order $knownOrder = null): void
    {
        $order = $knownOrder ?? wc_get_order($orderId);
        if (!$order instanceof \WC_Order || !(new ShipmentDispatchPolicy())->shouldDispatch($event, $order->get_payment_method(), $order->is_paid())) {
            return;
        }
        $settings = (array) get_option('theobroma_commerce_settings', []);
        if (($settings['cdek_enabled'] ?? 'no') !== 'yes') {
            return;
        }
        $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET') ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET') : (string) ($settings['cdek_client_secret'] ?? '');
        $clientId = (string) ($settings['cdek_client_id'] ?? '');
        if ($secret === '' || $clientId === '') {
            return;
        }

        try {
            $shipping = $this->shippingData($order);
            if ($shipping === null) {
                return;
            }
            $data = [
                'number' => (string) $order->get_order_number(),
                'tariff_code' => $shipping['tariff_code'],
                'delivery_kind' => $shipping['kind'],
                'cod' => $order->get_payment_method() === 'cod',
                'pickup_code' => (string) $order->get_meta('_theobroma_cdek_point', true),
                'recipient' => [
                    'name' => trim($order->get_formatted_billing_full_name()),
                    'phone' => $order->get_billing_phone(),
                    'email' => $order->get_billing_email(),
                ],
                'destination' => [
                    'country_code' => $order->get_shipping_country() ?: $order->get_billing_country(),
                    'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
                    'postal_code' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                    'address' => trim(($order->get_shipping_address_1() ?: $order->get_billing_address_1()) . ' ' . ($order->get_shipping_address_2() ?: $order->get_billing_address_2())),
                ],
                'items' => $this->items($order),
            ];
            $payload = (new CdekOrderPayloadFactory((int) ($settings['cdek_sender_city_code'] ?? 0), (string) ($settings['cdek_sender_address'] ?? '')))->build($data);
            $client = new CdekClient(new WpTransport(), new WordPressTokenStore(), $clientId, $secret);
            if (isset($payload['to_location'])) {
                $cities = $client->cities(['city' => $payload['to_location']['city'] ?? '', 'country_codes' => $payload['to_location']['country_code'] ?? 'RU', 'size' => 1]);
                $code = (int) ($cities[0]['code'] ?? 0);
                if ($code <= 0) {
                    throw new \InvalidArgumentException('Город СДЭК не найден. Проверьте адрес заказа.');
                }
                $payload['to_location']['code'] = $code;
            }
            (new CdekShipmentService($client))->create(new WooShipmentOrder($order), $payload);
        } catch (\Throwable $exception) {
            $order->add_order_note('Не удалось создать отправление СДЭК: ' . sanitize_text_field($exception->getMessage()));
            wc_get_logger()->error($exception->getMessage(), ['source' => 'theobroma-cdek', 'order_id' => $orderId]);
        }
    }

    /** @return array{tariff_code:int,kind:string}|null */
    private function shippingData(\WC_Order $order): ?array
    {
        foreach ($order->get_items('shipping') as $item) {
            if (!$item instanceof \WC_Order_Item_Shipping || $item->get_method_id() !== 'theobroma_cdek') {
                continue;
            }
            return [
                'tariff_code' => (int) $item->get_meta('theobroma_tariff_code', true),
                'kind' => (string) $item->get_meta('theobroma_delivery_kind', true),
            ];
        }
        return null;
    }

    /** @return list<array{sku:string,name:string,quantity:int,unit_price:float,weight_g:int}> */
    private function items(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            if (!$product instanceof \WC_Product || !$product->needs_shipping()) {
                continue;
            }
            $quantity = max(1, $item->get_quantity());
            $items[] = [
                'sku' => $product->get_sku() ?: (string) $product->get_id(),
                'name' => $item->get_name(),
                'quantity' => $quantity,
                'unit_price' => (float) $order->get_item_total($item, false, false),
                'weight_g' => (int) round((float) wc_get_weight((float) $product->get_weight(), 'g')),
            ];
        }
        return $items;
    }
}

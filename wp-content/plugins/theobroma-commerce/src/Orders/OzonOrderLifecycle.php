<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Support\ProviderException;

final class OzonOrderLifecycle
{
    public function register(): void
    {
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'formatDeliveryMeta'], 20, 2);
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
        if (!$order instanceof \WC_Order
            || !(new ShipmentDispatchPolicy())->shouldDispatch($event, $order->get_payment_method(), $order->is_paid())
            || !$this->usesOzonDelivery($order)) {
            return;
        }

        $settings = (array) get_option('theobroma_commerce_settings', []);
        $payload = (new OzonOrderPayloadResolver())->resolve(
            $order->get_meta('_theobroma_ozon_create_payload', true),
            $order->get_items('shipping')
        );
        if (!is_array($payload) || $payload === []) {
            $order->add_order_note('Заказ Ozon Доставки не создан: отсутствует подтверждённый результат расчёта доставки.');
            return;
        }

        $person = [
            'first_name' => trim($order->get_billing_first_name()),
            'last_name' => trim($order->get_billing_last_name()),
        ];
        $nameError = \Theobroma\Commerce\Checkout\DeliveryCustomerName::error($person);
        if ($nameError !== null) {
            $order->add_order_note('Отправление Ozon не создано. ' . $nameError);
            return;
        }
        foreach ($person as $key => $value) {
            $payload['buyer'][$key] = $value;
            $payload['recipient']['recipient_' . $key] = $value;
        }

        try {
            $client = (new OzonClientFactory(new WpTransport(), new WordPressTokenStore()))->clientFromSettings($settings);
            (new OzonOrderService($client))->create(new WooShipmentOrder($order), true, $payload);
        } catch (\Throwable $exception) {
            $reason = OzonFailureReason::describe($exception);
            $order->add_order_note('Не удалось создать отправление Ozon Доставки. ' . $reason);
            wc_get_logger()->error('Ozon order creation failed', [
                'source' => 'theobroma-ozon',
                'reason' => $reason,
                'order_id' => $orderId,
                'status' => $exception instanceof ProviderException ? $exception->statusCode() : 0,
            ]);
        }
    }

    /** Hide internal delivery data without deleting it from existing orders. */
    public function formatDeliveryMeta(array $metadata, $item): array
    {
        if (!$item instanceof \WC_Order_Item_Shipping) {
            return $metadata;
        }
        $labels = [
            'theobroma_pickup_point' => 'Код пункта выдачи',
            'theobroma_pickup_address' => 'Адрес пункта выдачи',
            'theobroma_delivery_kind' => 'Способ доставки',
        ];
        foreach ($metadata as $id => $meta) {
            if (!str_starts_with((string) $meta->key, 'theobroma_')) {
                continue;
            }
            if (!isset($labels[$meta->key])) {
                unset($metadata[$id]);
                continue;
            }
            $meta->display_key = $labels[$meta->key];
            if ($meta->key === 'theobroma_delivery_kind') {
                $meta->display_value = esc_html(['pickup' => 'Пункт выдачи', 'courier' => 'Курьер'][$meta->value] ?? (string) $meta->value);
            }
        }
        return $metadata;
    }

    private function usesOzonDelivery(\WC_Order $order): bool
    {
        foreach ($order->get_items('shipping') as $item) {
            if ($item instanceof \WC_Order_Item_Shipping && $item->get_method_id() === 'theobroma_ozon') {
                return true;
            }
        }
        return false;
    }
}

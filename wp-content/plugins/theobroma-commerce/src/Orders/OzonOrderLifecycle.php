<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClient;
use Theobroma\Commerce\Integrations\Ozon\OzonReadinessFactory;

final class OzonOrderLifecycle
{
    public function register(): void
    {
        add_action('woocommerce_order_status_processing', [$this, 'createShipment'], 20);
    }

    public function createShipment(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || !$order->is_paid() || !$this->usesOzonDelivery($order)) {
            return;
        }

        $settings = (array) get_option('theobroma_commerce_settings', []);
        $token = defined('THEOBROMA_OZON_ACCESS_TOKEN')
            ? (string) constant('THEOBROMA_OZON_ACCESS_TOKEN')
            : (string) ($settings['ozon_access_token'] ?? '');
        $capabilities = (new OzonReadinessFactory())->build(
            $settings,
            $token !== '',
            wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'objects'])
        );
        if (($settings['ozon_enabled'] ?? 'no') !== 'yes' || !$capabilities->canOfferDelivery()) {
            return;
        }

        $payload = $order->get_meta('_theobroma_ozon_create_payload', true);
        if (!is_array($payload) || $payload === []) {
            $order->add_order_note('Заказ Ozon Доставки не создан: отсутствует подтверждённый результат расчёта доставки.');
            return;
        }

        try {
            $client = new OzonClient(new WpTransport(), $token);
            (new OzonOrderService($client))->create(new WooShipmentOrder($order), true, $payload);
        } catch (\Throwable $exception) {
            $order->add_order_note('Не удалось создать заказ Ozon Доставки: ' . sanitize_text_field($exception->getMessage()));
            wc_get_logger()->error($exception->getMessage(), ['source' => 'theobroma-ozon', 'order_id' => $orderId]);
        }
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

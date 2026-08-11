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
        add_action('woocommerce_order_status_processing', [$this, 'createShipment'], 20);
    }

    public function createShipment(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || !$order->is_paid() || !$this->usesOzonDelivery($order)) {
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

        try {
            $client = (new OzonClientFactory(new WpTransport(), new WordPressTokenStore()))->clientFromSettings($settings);
            (new OzonOrderService($client))->create(new WooShipmentOrder($order), true, $payload);
        } catch (\Throwable $exception) {
            $order->add_order_note('Не удалось создать заказ Ozon Доставки. Проверьте журнал интеграции.');
            wc_get_logger()->error('Ozon order creation failed', [
                'source' => 'theobroma-ozon',
                'order_id' => $orderId,
                'status' => $exception instanceof ProviderException ? $exception->statusCode() : 0,
            ]);
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

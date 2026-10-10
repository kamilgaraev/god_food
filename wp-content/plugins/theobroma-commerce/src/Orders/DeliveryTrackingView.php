<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class DeliveryTrackingView
{
    public function render($order): void
    {
        if (!$order instanceof \WC_Order || !is_account_page() || !get_current_user_id() || (int) $order->get_customer_id() !== get_current_user_id()) {
            return;
        }
        $providers = [];
        foreach ($order->get_items('shipping') as $item) {
            if (in_array($item->get_method_id(), ['theobroma_ozon', 'theobroma_cdek'], true)) {
                $providers[$item->get_method_id()] = true;
            }
        }
        if ($providers === []) {
            return;
        }
        echo '<section class="woocommerce-order-delivery"><h2>Отслеживание доставки</h2>';
        foreach (array_keys($providers) as $provider) {
            $ozon = $provider === 'theobroma_ozon';
            echo '<h3>' . ($ozon ? 'Ozon Доставка' : 'СДЭК') . '</h3>';
            $numbers = $ozon ? (array) $order->get_meta('_theobroma_ozon_postings', true) : [(string) $order->get_meta('_theobroma_cdek_number', true)];
            $numbers = array_values(array_filter($numbers, 'is_string'));
            $numbers = array_values(array_filter($numbers, static fn ($value) => trim($value) !== ''));
            if ($ozon && $numbers === []) {
                $number = (string) $order->get_meta('_theobroma_ozon_order_number', true);
                if ($number !== '') $numbers[] = $number;
            }
            $created = $numbers !== [] || (!$ozon && $order->get_meta('_theobroma_cdek_uuid', true));
            $status = $order->get_status();
            $label = match ($status) {
                'cancelled' => 'Заказ отменён',
                'refunded' => 'Заказ возвращён',
                'completed' => 'Заказ выполнен',
                'shipped' => 'Передан в доставку',
                'in-transit' => 'В пути',
                'delivering' => 'Доставляется',
                'pickup-ready' => 'Ожидает получения',
                default => $created ? 'Отправление создано. Ожидаем обновления перевозчика.' : 'Отправление ещё не создано.',
            };
            echo '<p>' . esc_html($label) . '</p>';
            if ($numbers !== []) {
                echo '<p>Номер отправления: <strong>' . esc_html(implode(', ', $numbers)) . '</strong></p>';
            }
            $states = (array) $order->get_meta('_theobroma_delivery_tracking_states', true);
            $labels = ['shipped' => 'Передан в доставку', 'in-transit' => 'В пути', 'pickup-ready' => 'Ожидает получения', 'delivering' => 'Доставляется', 'delivered' => 'Вручено', 'awaiting_packaging' => 'Собирается', 'awaiting_deliver' => 'Готовится к передаче', 'cancelled' => 'Отправление отменено'];
            foreach ($states as $index => $state) {
                if (is_string($state) && isset($labels[$state])) {
                    echo '<p>' . esc_html(($numbers[$index] ?? 'Отправление') . ': ' . $labels[$state]) . '</p>';
                }
            }
            $checked = (int) $order->get_meta('_theobroma_delivery_tracking_checked', true);
            if ($checked > 0) {
                echo '<p><small>Последняя проверка: ' . esc_html(wp_date('d.m.Y H:i', $checked)) . '</small></p>';
            }
        }
        echo '<p>Статус обновляется автоматически по данным перевозчика.</p></section>';
    }
}

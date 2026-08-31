<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Checkout\DeliverySelection;
use Theobroma\Commerce\Checkout\DeliverySelectionStore;

final class DeliveryOrderMeta
{
    public function register(): void
    {
        add_action('woocommerce_checkout_create_order', [$this, 'save'], 30, 2);
    }

    /** @param array<string,mixed> $data */
    public function save(\WC_Order $order, array $data): void
    {
        $selection = (new DeliverySelectionStore())->load();
        if (!$selection instanceof DeliverySelection || !$selection->isConfirmed()) {
            return;
        }
        foreach (self::values($selection) as $key => $value) {
            $order->update_meta_data($key, $value);
        }
    }

    /** @return array<string,string> */
    public static function values(DeliverySelection $selection): array
    {
        $data = $selection->toArray();
        $provider = (string) $data['provider'];
        $point = (string) ($data['point']['id'] ?? '');
        $values = [
            '_theobroma_delivery_provider' => $provider,
            '_theobroma_delivery_kind' => (string) $data['kind'],
            '_theobroma_delivery_point' => $point,
        ];
        if ($provider === 'cdek') {
            $values['_theobroma_cdek_point'] = $point;
        } elseif ($provider === 'ozon') {
            $values['_theobroma_ozon_point'] = $point;
            $values['_theobroma_ozon_create_payload'] = (string) json_encode($data['create_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $values;
    }
}

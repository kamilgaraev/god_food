<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class OzonOrderPrices
{
    public static function money(float $amount, string $currency): array
    {
        if (!is_finite($amount) || $amount < 0) {
            throw new \InvalidArgumentException('Invalid order amount');
        }
        $nanos = (int) round($amount * 1000000000);
        return ['currency_code' => $currency, 'units' => intdiv($nanos, 1000000000), 'nanos' => $nanos % 1000000000];
    }

    /** Enrich the confirmed selection with actual order totals, never current catalogue prices. */
    public function apply(array $payload, \WC_Order $order): array
    {
        $lines = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product || !$product->needs_shipping()) {
                continue;
            }
            $sku = (string) $product->get_meta('_theobroma_ozon_sku', true);
            if ($sku === '' && $product->get_parent_id()) {
                $parent = wc_get_product($product->get_parent_id());
                $sku = $parent ? (string) $parent->get_meta('_theobroma_ozon_sku', true) : '';
            }
            if (!ctype_digit($sku) || (int) $sku <= 0 || $item->get_quantity() <= 0) {
                throw new \InvalidArgumentException('Order item has no valid Ozon SKU or quantity');
            }
            $lines[$sku] ??= ['quantity' => 0, 'total' => 0.0];
            $lines[$sku]['quantity'] += $item->get_quantity();
            $lines[$sku]['total'] += (float) $item->get_total() + (float) $item->get_total_tax();
        }
        $used = [];
        $splits = (array) ($payload['splits'] ?? []);
        if ($splits === []) {
            throw new \InvalidArgumentException('No confirmed Ozon splits');
        }
        foreach ($splits as &$split) {
            foreach ($split['items'] as &$item) {
                $sku = (string) ($item['sku'] ?? '');
                $quantity = (int) ($item['quantity'] ?? 0);
                if (!isset($lines[$sku]) || $quantity < 1) {
                    throw new \InvalidArgumentException('Ozon selection does not match order items');
                }
                $used[$sku] = ($used[$sku] ?? 0) + $quantity;
                $item['price'] = self::money($lines[$sku]['total'] / $lines[$sku]['quantity'], $order->get_currency());
            }
            unset($item);
            if (count($splits) === 1) {
                $split['delivery_method']['price'] = self::money((float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $order->get_currency());
            } elseif (empty($split['delivery_method']['price'])) {
                throw new \InvalidArgumentException('Recalculate delivery for multiple Ozon splits');
            }
        }
        unset($split);
        foreach ($lines as $sku => $line) {
            if (($used[$sku] ?? 0) !== $line['quantity']) {
                throw new \InvalidArgumentException('Ozon selection quantities differ from order');
            }
        }
        $payload['splits'] = $splits;
        return $payload;
    }
}

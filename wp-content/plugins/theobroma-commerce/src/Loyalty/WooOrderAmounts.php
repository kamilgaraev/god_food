<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class WooOrderAmounts
{
    public function paidMerchandiseKopecks(\WC_Order $order): int
    {
        return $this->lineItemsKopecks($order);
    }

    public function refundedMerchandiseKopecks(\WC_Order $refund): int
    {
        return abs($this->lineItemsKopecks($refund));
    }

    private function lineItemsKopecks(\WC_Order $order): int
    {
        $total = 0.0;
        foreach ($order->get_items('line_item') as $item) {
            $total += (float) $item->get_total() + (float) $item->get_total_tax();
        }

        return (int) round($total * 100);
    }
}

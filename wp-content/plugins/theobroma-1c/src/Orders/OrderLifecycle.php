<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final class OrderLifecycle
{
    private bool $saving = false;

    public function __construct(private readonly ExportPolicy $policy = new ExportPolicy()) {}

    public function register(): void
    {
        foreach (['woocommerce_payment_complete', 'woocommerce_order_status_changed', 'woocommerce_order_refunded', 'woocommerce_update_order'] as $hook) {
            add_action($hook, [$this, 'queue'], 20);
        }
    }

    public function queue(int $orderId): void
    {
        if ($this->saving) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $state = $this->state($order);
        if (!$this->policy->shouldQueue($order->is_paid(), $state)) {
            return;
        }

        $fingerprint = OrderFingerprint::hash($this->snapshot($order));
        if (hash_equals((string) $order->get_meta('_theobroma_1c_fingerprint', true), $fingerprint)) {
            return;
        }

        $this->saving = true;
        try {
            $order->update_meta_data('_theobroma_1c_revision', $state->queue()->revision);
            $order->update_meta_data('_theobroma_1c_fingerprint', $fingerprint);
            $order->save();
        } finally {
            $this->saving = false;
        }
    }

    public function state(\WC_Order $order): ExportState
    {
        return new ExportState(
            (int) $order->get_meta('_theobroma_1c_revision', true),
            (int) $order->get_meta('_theobroma_1c_ack_revision', true),
            (string) $order->get_meta('_theobroma_1c_exported_at', true)
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items(['line_item', 'shipping', 'fee', 'coupon']) as $item) {
            $items[] = [
                'id' => (int) $item->get_id(),
                'name' => (string) $item->get_name(),
                'product_id' => method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0,
                'variation_id' => method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0,
                'quantity' => method_exists($item, 'get_quantity') ? (string) $item->get_quantity() : '',
                'subtotal' => method_exists($item, 'get_subtotal') ? (string) $item->get_subtotal() : '',
                'total' => method_exists($item, 'get_total') ? (string) $item->get_total() : '',
                'tax' => method_exists($item, 'get_total_tax') ? (string) $item->get_total_tax() : '',
            ];
        }

        $refunds = [];
        foreach ($order->get_refunds() as $refund) {
            $refunds[] = [(int) $refund->get_id(), (string) $refund->get_total(), $refund->get_date_created()?->date(DATE_ATOM) ?? ''];
        }

        return [
            'status' => (string) $order->get_status(),
            'currency' => (string) $order->get_currency(),
            'total' => (string) $order->get_total(),
            'paid_at' => $order->get_date_paid()?->date(DATE_ATOM) ?? '',
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => (string) $order->get_payment_method(),
            'customer_note' => (string) $order->get_customer_note(),
            'coupons' => $order->get_coupon_codes(),
            'items' => $items,
            'refunds' => $refunds,
            'bonus_spent' => (int) $order->get_meta('_theobroma_bonus_spent_kopecks', true),
            'bonus_accrued' => (int) $order->get_meta('_theobroma_bonus_accrued_kopecks', true),
        ];
    }
}

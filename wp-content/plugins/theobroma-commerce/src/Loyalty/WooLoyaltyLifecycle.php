<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class WooLoyaltyLifecycle
{
    private LoyaltyService $service;

    public function __construct(
        ?LoyaltyStore $store = null,
        private readonly LoyaltyCalculator $calculator = new LoyaltyCalculator(),
        private readonly WooOrderAmounts $amounts = new WooOrderAmounts()
    ) {
        if (!$store instanceof LoyaltyStore) {
            global $wpdb;
            $store = new WpdbLoyaltyStore($wpdb);
        }
        $this->service = new LoyaltyService($store);
    }

    public function register(): void
    {
        add_action('woocommerce_payment_complete', [$this, 'onPaid'], 20);
        add_action('woocommerce_order_status_processing', [$this, 'onPaid'], 30);
        add_action('woocommerce_order_status_completed', [$this, 'onPaid'], 30);
        add_action('woocommerce_order_status_failed', [$this, 'onUnpaidTerminal'], 20);
        add_action('woocommerce_order_status_cancelled', [$this, 'onCancelled'], 20);
        add_action('woocommerce_order_refunded', [$this, 'onRefunded'], 20, 2);
    }

    public function onPaid(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || !$order->is_paid() || $order->get_customer_id() < 1) {
            return;
        }

        try {
            $userId = (int) $order->get_customer_id();
            $reserved = max(0, (int) $order->get_meta('_theobroma_bonus_reserved_kopecks', true));
            $spent = max(0, (int) $order->get_meta('_theobroma_bonus_spent_kopecks', true));
            if ($reserved > 0 && $spent < $reserved) {
                $this->service->spend($userId, $orderId, $reserved);
                $order->update_meta_data('_theobroma_bonus_spent_kopecks', $reserved);
            }

            $accrual = $this->calculator->accrual($this->amounts->paidMerchandiseKopecks($order));
            $accrued = max(0, (int) $order->get_meta('_theobroma_bonus_accrued_kopecks', true));
            if ($accrual > 0 && $accrued < $accrual) {
                $this->service->accrue($userId, $orderId, $accrual);
                $order->update_meta_data('_theobroma_bonus_accrued_kopecks', $accrual);
                $order->add_order_note(sprintf('Начислено бонусов: %s.', wc_price($accrual / 100)));
            }
            $order->save();
        } catch (\Throwable $exception) {
            $this->logFailure($order, 'Не удалось обработать бонусы после оплаты.', $exception);
        }
    }

    public function onUnpaidTerminal(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || $order->get_customer_id() < 1) {
            return;
        }

        $reserved = max(0, (int) $order->get_meta('_theobroma_bonus_reserved_kopecks', true));
        $spent = max(0, (int) $order->get_meta('_theobroma_bonus_spent_kopecks', true));
        $released = max(0, (int) $order->get_meta('_theobroma_bonus_released_kopecks', true));
        if ($reserved < 1 || $spent > 0 || $released >= $reserved) {
            return;
        }

        try {
            $this->service->release((int) $order->get_customer_id(), $orderId, $reserved);
            $order->update_meta_data('_theobroma_bonus_released_kopecks', $reserved);
            $order->save();
        } catch (\Throwable $exception) {
            $this->logFailure($order, 'Не удалось освободить резерв бонусов.', $exception);
        }
    }

    public function onCancelled(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || $order->get_customer_id() < 1) {
            return;
        }

        $spent = max(0, (int) $order->get_meta('_theobroma_bonus_spent_kopecks', true));
        $accrued = max(0, (int) $order->get_meta('_theobroma_bonus_accrued_kopecks', true));
        if ($spent < 1 && $accrued < 1) {
            $this->onUnpaidTerminal($orderId);
            return;
        }

        $this->reverse($order, 0, $accrued, $spent);
    }

    public function onRefunded(int $orderId, int $refundId): void
    {
        $order = wc_get_order($orderId);
        $refund = wc_get_order($refundId);
        if (!$order instanceof \WC_Order || !$refund instanceof \WC_Order || $order->get_customer_id() < 1) {
            return;
        }

        $original = $this->amounts->paidMerchandiseKopecks($order);
        if ($original < 1) {
            return;
        }

        $refunded = 0;
        foreach ($order->get_refunds() as $existingRefund) {
            if ($existingRefund instanceof \WC_Order) {
                $refunded += $this->amounts->refundedMerchandiseKopecks($existingRefund);
            }
        }
        if ($refunded < 1) {
            $refunded = $this->amounts->refundedMerchandiseKopecks($refund);
        }

        $accrued = max(0, (int) $order->get_meta('_theobroma_bonus_accrued_kopecks', true));
        $spent = max(0, (int) $order->get_meta('_theobroma_bonus_spent_kopecks', true));
        $ratioNumerator = min($original, $refunded);
        $targetAccrualReversal = intdiv($accrued * $ratioNumerator, $original);
        $targetSpendRestore = intdiv($spent * $ratioNumerator, $original);
        if ($ratioNumerator >= $original) {
            $targetAccrualReversal = $accrued;
            $targetSpendRestore = $spent;
        }

        $alreadyReversed = max(0, (int) $order->get_meta('_theobroma_bonus_reversed_kopecks', true));
        $alreadyRestored = max(0, (int) $order->get_meta('_theobroma_bonus_restored_kopecks', true));
        $this->reverse(
            $order,
            $refundId,
            max(0, $targetAccrualReversal - $alreadyReversed),
            max(0, $targetSpendRestore - $alreadyRestored)
        );
    }

    private function reverse(\WC_Order $order, int $refundId, int $accrualKopecks, int $spendKopecks): void
    {
        try {
            $userId = (int) $order->get_customer_id();
            $orderId = (int) $order->get_id();
            if ($spendKopecks > 0) {
                $this->service->restoreSpend($userId, $orderId, $refundId, $spendKopecks);
                $restored = max(0, (int) $order->get_meta('_theobroma_bonus_restored_kopecks', true));
                $order->update_meta_data('_theobroma_bonus_restored_kopecks', $restored + $spendKopecks);
            }
            if ($accrualKopecks > 0) {
                $this->service->reverseAccrual($userId, $orderId, $refundId, $accrualKopecks);
                $reversed = max(0, (int) $order->get_meta('_theobroma_bonus_reversed_kopecks', true));
                $order->update_meta_data('_theobroma_bonus_reversed_kopecks', $reversed + $accrualKopecks);
            }
            $order->save();
        } catch (\Throwable $exception) {
            $this->logFailure($order, 'Не удалось скорректировать бонусы при отмене или возврате.', $exception);
        }
    }

    private function logFailure(\WC_Order $order, string $customerSafeMessage, \Throwable $exception): void
    {
        $order->add_order_note($customerSafeMessage);
        wc_get_logger()->error($exception->getMessage(), [
            'source' => 'theobroma-loyalty',
            'order_id' => $order->get_id(),
        ]);
    }
}

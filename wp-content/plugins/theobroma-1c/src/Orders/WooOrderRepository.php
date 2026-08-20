<?php
declare(strict_types=1);

namespace Theobroma\OneC\Orders;

final class WooOrderRepository
{
    /** @return list<array{order: \WC_Order, revision: int}> */
    public function pending(int $limit): array
    {
        $page = 1;
        $pageSize = max(100, $limit);
        $pending = [];

        do {
            $orders = wc_get_orders([
                'limit' => $pageSize,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'ASC',
                'meta_query' => [[
                    'key' => '_theobroma_1c_revision',
                    'compare' => 'EXISTS',
                ]],
            ]);

            foreach ($orders as $order) {
                $revision = (int) $order->get_meta('_theobroma_1c_revision', true);
                $acknowledgedRevision = (int) $order->get_meta('_theobroma_1c_ack_revision', true);
                if ($revision <= $acknowledgedRevision) {
                    continue;
                }

                $pending[] = ['order' => $order, 'revision' => $revision];
                if (count($pending) >= $limit) {
                    return $pending;
                }
            }

            $page++;
        } while (count($orders) === $pageSize);

        return $pending;
    }

    /** @param list<array{order_id: int, revision: int}> $batch */
    public function acknowledge(array $batch): void
    {
        foreach ($batch as $row) {
            $order = wc_get_order($row['order_id']);
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $current = (int) $order->get_meta('_theobroma_1c_ack_revision', true);
            if ($row['revision'] > $current) {
                $order->update_meta_data('_theobroma_1c_ack_revision', $row['revision']);
                $order->update_meta_data('_theobroma_1c_exported_at', gmdate('c'));
                $order->save();
            }
        }
    }
}

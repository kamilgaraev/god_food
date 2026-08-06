<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Integrations\Ozon\OzonOrderApi;

final class OzonOrderService
{
    private const ORDER_ID_META = '_theobroma_ozon_order_id';
    private const POSTINGS_META = '_theobroma_ozon_postings';

    public function __construct(private readonly OzonOrderApi $api)
    {
    }

    /** @param array<mixed> $payload */
    public function create(ShipmentOrder $order, bool $paid, array $payload): ?int
    {
        if (!$paid) {
            return null;
        }

        $existing = (int) $order->get(self::ORDER_ID_META);
        if ($existing > 0) {
            return $existing;
        }

        $response = $this->api->createOrder($payload);
        $orderId = (int) ($response['order_id'] ?? 0);
        if ($orderId <= 0) {
            throw new \RuntimeException('Ozon accepted the request but did not confirm order creation');
        }

        $postingNumbers = [];
        foreach ((array) ($response['postings'] ?? []) as $posting) {
            if (!is_array($posting) || trim((string) ($posting['posting_number'] ?? '')) === '') {
                continue;
            }
            $postingNumbers[] = trim((string) $posting['posting_number']);
        }

        $order->set(self::ORDER_ID_META, $orderId);
        $order->set(self::POSTINGS_META, array_values(array_unique($postingNumbers)));
        $order->note(sprintf('Заказ Ozon Доставки создан: %d', $orderId));
        $order->save();

        return $orderId;
    }
}

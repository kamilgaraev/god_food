<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Integrations\Ozon\OzonOrderApi;

final class OzonOrderService
{
    private const ORDER_NUMBER_META = '_theobroma_ozon_order_number';
    private const LEGACY_ORDER_ID_META = '_theobroma_ozon_order_id';
    private const POSTINGS_META = '_theobroma_ozon_postings';

    public function __construct(private readonly OzonOrderApi $api)
    {
    }

    /** @param array<mixed> $payload */
    public function create(ShipmentOrder $order, bool $paid, array $payload): ?string
    {
        if (!$paid) {
            return null;
        }

        $existing = trim((string) $order->get(self::ORDER_NUMBER_META));
        if ($existing === '') {
            $existing = trim((string) $order->get(self::LEGACY_ORDER_ID_META));
        }
        if ($existing !== '') {
            return $existing;
        }

        $response = $this->api->createOrder($payload);
        $orderNumber = trim((string) ($response['order_number'] ?? ''));
        if ($orderNumber === '') {
            throw new \RuntimeException('Ozon accepted the request but did not confirm order creation');
        }

        $postingNumbers = [];
        foreach ((array) ($response['postings'] ?? []) as $posting) {
            $postingNumber = is_array($posting)
                ? trim((string) ($posting['posting_number'] ?? ''))
                : trim((string) $posting);
            if ($postingNumber === '') {
                continue;
            }
            $postingNumbers[] = $postingNumber;
        }

        $order->set(self::ORDER_NUMBER_META, $orderNumber);
        $order->set(self::POSTINGS_META, array_values(array_unique($postingNumbers)));
        $order->note(sprintf('Заказ Ozon Доставки создан: %s', $orderNumber));
        $order->save();

        return $orderNumber;
    }
}

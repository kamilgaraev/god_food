<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Integrations\Cdek\CdekOrderApi;

final class CdekShipmentService
{
    private const UUID_META = '_theobroma_cdek_uuid';

    public function __construct(private readonly CdekOrderApi $api)
    {
    }

    /** @param array<mixed> $payload */
    public function create(ShipmentOrder $order, array $payload): string
    {
        $existing = (string) ($order->get(self::UUID_META) ?? '');
        if ($existing !== '') {
            return $existing;
        }

        $entity = $this->api->createOrder($payload);
        $uuid = (string) ($entity['uuid'] ?? '');
        if ($uuid === '') {
            throw new \RuntimeException('CDEK order UUID is missing');
        }

        $order->set(self::UUID_META, $uuid);
        $order->note(sprintf('Отправление СДЭК создано: %s', $uuid));
        $order->save();

        return $uuid;
    }
}

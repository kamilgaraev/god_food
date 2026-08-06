<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Integrations\Cdek\CdekOrderApi;

final class CdekStatusSynchronizer
{
    public function __construct(private readonly CdekOrderApi $api)
    {
    }

    public function sync(ShipmentOrder $order, string $expectedUuid): string
    {
        $entity = $this->api->getOrder($expectedUuid);
        if (!hash_equals($expectedUuid, (string) ($entity['uuid'] ?? ''))) {
            throw new \RuntimeException('CDEK returned a different order');
        }

        $statuses = array_values(array_filter(
            (array) ($entity['statuses'] ?? []),
            static fn (mixed $status): bool => is_array($status) && trim((string) ($status['code'] ?? '')) !== ''
        ));
        if ($statuses === []) {
            throw new \RuntimeException('CDEK order status is missing');
        }
        usort($statuses, static fn (array $left, array $right): int => strcmp(
            (string) ($left['date_time'] ?? ''),
            (string) ($right['date_time'] ?? '')
        ));
        $latest = $statuses[array_key_last($statuses)];
        $status = trim((string) $latest['code']);

        $previous = (string) ($order->get('_theobroma_cdek_status') ?? '');
        $order->set('_theobroma_cdek_status', $status);
        $order->set('_theobroma_cdek_status_at', (string) ($latest['date_time'] ?? ''));
        if (trim((string) ($entity['cdek_number'] ?? '')) !== '') {
            $order->set('_theobroma_cdek_number', trim((string) $entity['cdek_number']));
        }
        if ($previous !== $status) {
            $order->note(sprintf('Статус СДЭК: %s', $status));
        }
        $order->save();

        return $status;
    }
}

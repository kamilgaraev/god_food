<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class OzonOrderPayloadResolver
{
    /** @param iterable<object> $shippingItems @return array<mixed>|null */
    public function resolve(mixed $orderPayload, iterable $shippingItems): ?array
    {
        $payload = $this->normalize($orderPayload);
        if ($payload !== null) {
            return $payload;
        }

        foreach ($shippingItems as $item) {
            if (!method_exists($item, 'get_meta')) {
                continue;
            }
            $payload = $this->normalize($item->get_meta('theobroma_ozon_create_payload', true));
            if ($payload !== null) {
                return $payload;
            }
        }

        return null;
    }

    /** @return array<mixed>|null */
    private function normalize(mixed $payload): ?array
    {
        if (is_string($payload) && trim($payload) !== '') {
            $payload = json_decode($payload, true);
        }
        return is_array($payload) && $payload !== [] ? $payload : null;
    }
}

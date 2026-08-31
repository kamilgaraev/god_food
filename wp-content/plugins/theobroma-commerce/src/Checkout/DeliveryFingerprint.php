<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryFingerprint
{
    /** @param list<array<string,mixed>> $items @param array<string,mixed> $destination */
    public static function fromData(array $items, array $destination): string
    {
        $lines = array_map(static fn (array $item): array => [
            'product_id' => (int) ($item['product_id'] ?? 0),
            'variation_id' => (int) ($item['variation_id'] ?? 0),
            'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
        ], $items);
        usort($lines, static fn (array $left, array $right): int => [$left['product_id'], $left['variation_id']] <=> [$right['product_id'], $right['variation_id']]);

        $address = [
            'country' => strtoupper(trim((string) ($destination['country'] ?? 'RU'))),
            'state' => trim((string) ($destination['state'] ?? '')),
            'city' => mb_strtolower(trim((string) ($destination['city'] ?? ''))),
            'postcode' => preg_replace('/\s+/', '', (string) ($destination['postcode'] ?? '')) ?? '',
            'address' => mb_strtolower(trim((string) ($destination['address'] ?? ''))),
        ];

        return hash('sha256', (string) json_encode(['items' => $lines, 'destination' => $address], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

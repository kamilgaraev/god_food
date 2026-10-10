<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

final class CdekOrderPayloadFactory
{
    public function __construct(private readonly int $senderCityCode, private readonly string $senderAddress)
    {
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function build(array $data): array
    {
        $recipient = (array) ($data['recipient'] ?? []);
        $items = [];
        $packageWeight = 0;
        foreach ((array) ($data['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $weight = max(1, (int) ($item['weight_g'] ?? 0));
            $packageWeight += $weight * $quantity;
            $items[] = [
                'ware_key' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'cost' => (float) ($item['unit_price'] ?? 0),
                'payment' => ['value' => !empty($data['cod']) ? (float) ($item['unit_price'] ?? 0) : 0.0],
                'weight' => $weight,
                'amount' => $quantity,
            ];
        }
        if ($items === [] || $packageWeight <= 0) {
            throw new \InvalidArgumentException('CDEK order requires weighted items');
        }

        $payload = [
            'number' => (string) ($data['number'] ?? ''),
            'type' => 1,
            'tariff_code' => (int) ($data['tariff_code'] ?? 0),
            'recipient' => [
                'name' => (string) ($recipient['name'] ?? ''),
                'email' => (string) ($recipient['email'] ?? ''),
                'phones' => [['number' => (string) ($recipient['phone'] ?? '')]],
            ],
            'from_location' => [
                'code' => $this->senderCityCode,
                'address' => $this->senderAddress,
            ],
            'packages' => [[
                'number' => (string) ($data['number'] ?? '') . '-1',
                'weight' => $packageWeight,
                'items' => $items,
            ]],
        ];

        $destination = (array) ($data['destination'] ?? []);
        if (($data['delivery_kind'] ?? '') === 'pickup') {
            $payload['delivery_point'] = (string) ($data['pickup_code'] ?? '');
        } else {
            $payload['to_location'] = array_filter([
                'country_code' => (string) ($destination['country_code'] ?? ''),
                'postal_code' => (string) ($destination['postal_code'] ?? ''),
                'city' => (string) ($destination['city'] ?? ''),
                'address' => (string) ($destination['address'] ?? ''),
            ], static fn (string $value): bool => $value !== '');
        }

        if ($payload['tariff_code'] <= 0) {
            throw new \InvalidArgumentException('CDEK tariff code is required');
        }
        if (($data['delivery_kind'] ?? '') === 'pickup' && ($payload['delivery_point'] ?? '') === '') {
            throw new \InvalidArgumentException('CDEK pickup point is required');
        }

        return $payload;
    }
}

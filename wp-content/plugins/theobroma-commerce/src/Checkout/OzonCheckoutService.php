<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

use Theobroma\Commerce\Integrations\Ozon\OzonClient;

final class OzonCheckoutService
{
    public function __construct(private readonly OzonClient $client)
    {
    }

    /** @param array<string,mixed> $viewport @return list<array{id:string,name:string,address:string,work_time:string,latitude:float|null,longitude:float|null}> */
    public function points(array $viewport): array
    {
        $response = $this->client->deliveryPointList($viewport);
        $points = is_array($response['points'] ?? null) ? $response['points'] : $response;
        $result = [];
        foreach ($points as $point) {
            if (!is_array($point)) {
                continue;
            }
            $normalized = $this->normalizePoint($point);
            if ($normalized['id'] !== '') {
                $result[] = $normalized;
            }
        }
        return $result;
    }

    /** @return array{id:string,name:string,address:string,work_time:string,latitude:float|null,longitude:float|null} */
    public function point(string $id): array
    {
        $response = $this->client->deliveryPointInfo(['map_point_id' => (int) $id]);
        $point = is_array($response['point'] ?? null) ? $response['point'] : $response;
        return $this->normalizePoint($point);
    }

    /**
     * @param array{first_name?:string,last_name?:string,middle_name?:string,phone?:string} $buyer
     * @param array<string,mixed> $delivery
     * @param list<array{offer_id:string,quantity:int,sku:int}> $items
     * @param array{first_name?:string,last_name?:string,middle_name?:string,phone?:string} $recipient
     */
    public function quote(array $buyer, array $delivery, array $items, array $recipient): DeliveryQuote
    {
        $phone = preg_replace('/\D+/', '', (string) ($buyer['phone'] ?? '')) ?? '';
        if ($phone === '' || $delivery === [] || $items === []) {
            throw new \InvalidArgumentException('Ozon checkout data is incomplete');
        }

        $availability = $this->client->deliveryCheck([
            'buyer_phone' => $phone,
            'delivery_type' => $delivery,
        ]);
        if (($availability['available'] ?? true) === false) {
            throw new \RuntimeException('Ozon delivery is unavailable for this buyer');
        }

        $checkout = $this->client->deliveryCheckout([
            'buyer_phone' => $phone,
            'delivery_schema' => 'MIX',
            'delivery_type' => $delivery,
            'items' => $items,
        ]);
        $splits = array_values(array_filter((array) ($checkout['splits'] ?? []), 'is_array'));
        if ($splits === []) {
            throw new \RuntimeException('Ozon returned no delivery options');
        }

        $createSplits = [];
        $cost = 0.0;
        $labels = [];
        $deliverySchema = '';
        foreach ($splits as $split) {
            $method = is_array($split['delivery_method'] ?? null) ? $split['delivery_method'] : [];
            $timeslots = array_values(array_filter((array) ($method['timeslots'] ?? []), 'is_array'));
            $timeslot = $timeslots[0] ?? [];
            $deliverySchema = $deliverySchema !== '' ? $deliverySchema : (string) ($split['delivery_schema'] ?? 'MIX');
            $cost += $this->money((array) ($split['commissions']['total'] ?? $method['price'] ?? []));
            $labels[] = trim((string) ($method['name'] ?? 'Ozon Доставка'));
            $createSplits[] = [
                'delivery_method' => [
                    'delivery_method_id' => (int) ($method['id'] ?? $method['delivery_method_id'] ?? 0),
                    'delivery_type' => (string) ($method['delivery_type'] ?? ''),
                    'logistic_date_range' => (array) ($timeslot['logistic_date_range'] ?? []),
                    'timeslot_id' => (int) ($timeslot['timeslot_id'] ?? 0),
                ],
                'items' => array_values(array_filter((array) ($split['items'] ?? []), 'is_array')),
                'warehouse_id' => (int) ($split['warehouse_id'] ?? 0),
            ];
        }

        $kind = isset($delivery['pick_up']) ? 'pickup' : 'courier';
        $buyerPayload = $this->person($buyer, false);
        $recipientPayload = $this->person($recipient, true);
        $label = implode(' + ', array_values(array_unique(array_filter($labels)))) ?: 'Ozon Доставка';

        return new DeliveryQuote('ozon', $kind, $cost, $label, [
            'buyer' => $buyerPayload,
            'delivery' => $delivery,
            'delivery_schema' => $deliverySchema ?: 'MIX',
            'recipient' => $recipientPayload,
            'splits' => $createSplits,
        ]);
    }

    /** @param array<string,mixed> $point @return array{id:string,name:string,address:string,work_time:string,latitude:float|null,longitude:float|null} */
    private function normalizePoint(array $point): array
    {
        $coordinate = is_array($point['coordinate'] ?? null) ? $point['coordinate'] : [];
        return [
            'id' => trim((string) ($point['map_point_id'] ?? $point['id'] ?? '')),
            'name' => trim((string) ($point['name'] ?? 'Пункт выдачи Ozon')),
            'address' => trim((string) ($point['address'] ?? $point['full_address'] ?? '')),
            'work_time' => trim((string) ($point['work_time'] ?? $point['working_hours'] ?? '')),
            'latitude' => isset($coordinate['lat']) ? (float) $coordinate['lat'] : null,
            'longitude' => isset($coordinate['long']) ? (float) $coordinate['long'] : null,
        ];
    }

    /** @param array<string,mixed> $money */
    private function money(array $money): float
    {
        if (isset($money['amount'])) {
            return max(0.0, (float) $money['amount']);
        }
        return max(0.0, (float) ($money['units'] ?? 0) + ((float) ($money['nanos'] ?? 0) / 1_000_000_000));
    }

    /** @param array<string,mixed> $person @return array<string,string> */
    private function person(array $person, bool $recipient): array
    {
        $prefix = $recipient ? 'recipient_' : '';
        return [
            $prefix . 'first_name' => trim((string) ($person['first_name'] ?? '')),
            $prefix . 'last_name' => trim((string) ($person['last_name'] ?? '')),
            $prefix . 'middle_name' => trim((string) ($person['middle_name'] ?? '')),
            $prefix . 'phone' => preg_replace('/\D+/', '', (string) ($person['phone'] ?? '')) ?? '',
        ];
    }
}

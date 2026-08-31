<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Shipping\CdekRateSelector;

final class CdekCheckoutService
{
    public function __construct(private readonly CdekClient $client)
    {
    }

    /** @return list<array{id:string,name:string,address:string,work_time:string,latitude:float|null,longitude:float|null}> */
    public function points(string $city): array
    {
        $cities = $this->client->cities(['city' => trim($city), 'country_codes' => 'RU', 'size' => 1]);
        $cityCode = (int) ($cities[0]['code'] ?? 0);
        if ($cityCode <= 0) {
            return [];
        }

        $result = [];
        foreach ($this->client->deliveryPoints(['city_code' => $cityCode, 'type' => 'PVZ', 'is_handout' => 1]) as $point) {
            $id = trim((string) ($point['code'] ?? ''));
            if ($id === '') {
                continue;
            }
            $location = is_array($point['location'] ?? null) ? $point['location'] : [];
            $result[] = [
                'id' => $id,
                'name' => trim((string) ($point['name'] ?? 'ПВЗ СДЭК')),
                'address' => trim((string) ($location['address_full'] ?? $location['address'] ?? '')),
                'work_time' => trim((string) ($point['work_time'] ?? '')),
                'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
                'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
            ];
        }
        return $result;
    }

    /** @param array<string,mixed> $packagePayload */
    public function quote(array $packagePayload, string $kind): DeliveryQuote
    {
        $rate = (new CdekRateSelector())->cheapest($this->client->calculateTariffs($packagePayload), $kind);
        if (!is_array($rate)) {
            throw new \RuntimeException('CDEK delivery is unavailable for this destination');
        }
        $minimum = max(0, (int) ($rate['period_min'] ?? 0));
        $maximum = max($minimum, (int) ($rate['period_max'] ?? $minimum));
        $period = $maximum > 0 ? sprintf(', %d–%d дн.', $minimum, $maximum) : '';
        $label = ($kind === 'pickup' ? 'СДЭК — пункт выдачи' : 'СДЭК — курьер') . $period;

        return new DeliveryQuote('cdek', $kind, (float) $rate['delivery_sum'], $label, [
            'tariff_code' => (int) ($rate['tariff_code'] ?? 0),
            'delivery_kind' => $kind,
        ]);
    }
}

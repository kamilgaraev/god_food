<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryRateResolver
{
    public function __construct(private readonly DeliverySelectionStore $store)
    {
    }

    /** @return array{kind:string,label:string,cost:float,requires_selection:bool,meta_data:array<string,mixed>} */
    public function resolve(string $provider, string $fingerprint): array
    {
        $selection = $this->store->confirmedFor($provider, $fingerprint);
        if (!$selection instanceof DeliverySelection) {
            return [
                'kind' => 'bootstrap',
                'label' => $provider === 'ozon' ? 'Ozon Доставка' : 'СДЭК',
                'cost' => 0.0,
                'requires_selection' => true,
                'meta_data' => [
                    'theobroma_provider' => $provider,
                    'theobroma_requires_selection' => 'yes',
                ],
            ];
        }

        $data = $selection->toArray();
        $payload = (array) $data['create_payload'];
        $meta = [
            'theobroma_provider' => $provider,
            'theobroma_delivery_kind' => (string) $data['kind'],
            'theobroma_pickup_point' => (string) ($data['point']['id'] ?? ''),
            'theobroma_pickup_address' => (string) ($data['point']['address'] ?? ''),
        ];
        if ($provider === 'cdek') {
            $meta['theobroma_tariff_code'] = (int) ($payload['tariff_code'] ?? 0);
        } else {
            $meta['theobroma_ozon_create_payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return [
            'kind' => (string) $data['kind'],
            'label' => (string) $data['quote']['label'],
            'cost' => (float) $data['quote']['cost'],
            'requires_selection' => false,
            'meta_data' => $meta,
        ];
    }
}

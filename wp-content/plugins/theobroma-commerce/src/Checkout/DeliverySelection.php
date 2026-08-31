<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliverySelection
{
    /** @param array<string,mixed> $data */
    private function __construct(private readonly array $data)
    {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $rawProvider = mb_strtolower(trim((string) ($data['provider'] ?? '')));
        $provider = str_contains($rawProvider, 'ozon') ? 'ozon' : (str_contains($rawProvider, 'cdek') ? 'cdek' : '');
        $kind = in_array((string) ($data['kind'] ?? ''), ['pickup', 'courier'], true) ? (string) $data['kind'] : '';
        $point = is_array($data['point'] ?? null) ? $data['point'] : [];
        $quote = is_array($data['quote'] ?? null) ? $data['quote'] : [];
        $payload = is_array($data['create_payload'] ?? null) ? $data['create_payload'] : [];

        return new self([
            'version' => 1,
            'provider' => $provider,
            'kind' => $kind,
            'fingerprint' => trim((string) ($data['fingerprint'] ?? '')),
            'point' => [
                'id' => trim((string) ($point['id'] ?? '')),
                'name' => trim((string) ($point['name'] ?? '')),
                'address' => trim((string) ($point['address'] ?? '')),
                'latitude' => isset($point['latitude']) ? (float) $point['latitude'] : null,
                'longitude' => isset($point['longitude']) ? (float) $point['longitude'] : null,
            ],
            'quote' => [
                'cost' => isset($quote['cost']) ? max(0.0, (float) $quote['cost']) : null,
                'label' => trim((string) ($quote['label'] ?? '')),
            ],
            'create_payload' => $payload,
        ]);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function provider(): string
    {
        return (string) $this->data['provider'];
    }

    public function fingerprint(): string
    {
        return (string) $this->data['fingerprint'];
    }

    public function isConfirmed(): bool
    {
        return $this->provider() !== ''
            && $this->fingerprint() !== ''
            && in_array($this->data['kind'], ['pickup', 'courier'], true)
            && is_float($this->data['quote']['cost'])
            && $this->data['quote']['label'] !== ''
            && $this->data['create_payload'] !== [];
    }
}

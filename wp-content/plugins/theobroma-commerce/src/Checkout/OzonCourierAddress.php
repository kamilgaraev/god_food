<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class OzonCourierAddress
{
    /** @param array<string,mixed> $destination @param array<string,mixed> $courier @return array<string,mixed> */
    public function build(array $destination, array $courier = []): array
    {
        $country = trim((string) ($destination['country'] ?? $courier['country'] ?? ''));
        $courier['country'] = strtoupper($country) === 'RU' ? 'Россия' : $country;
        $courier['city'] = trim((string) ($destination['city'] ?? $courier['city'] ?? ''));
        $address = trim((string) ($destination['address'] ?? $destination['address_1'] ?? ''));

        if ($address !== '') {
            // Both address suggesters persist the street and house in one WooCommerce field.
            if (preg_match('/^(.*?)\s*(?:,\s*|\s+)(?:д(?:ом)?\.?\s*)?(\d+[\p{L}]?(?:\s*[\/\-]\s*\d+[\p{L}]?)?(?:\s*(?:к(?:орпус)?\.?|корп\.?|стр(?:оение)?\.?)\s*\d+[\p{L}]?)*)$/ui', $address, $parts) === 1) {
                $courier['street'] = trim($parts[1], " ,\t\n\r\0\x0B");
                $courier['house_number'] = trim($parts[2]);
            } elseif (trim((string) ($destination['house_number'] ?? $courier['house_number'] ?? '')) === '') {
                throw new \InvalidArgumentException('Ozon courier address is incomplete: missing=house_number');
            }
        }
        if (trim((string) ($destination['house_number'] ?? '')) !== '') {
            $courier['house_number'] = trim((string) $destination['house_number']);
        }

        foreach (['country', 'city', 'house_number'] as $field) {
            $value = trim((string) ($courier[$field] ?? ''));
            if ($value === '' || ($field === 'country' && mb_strlen($value) > 50)) {
                throw new \InvalidArgumentException('Ozon courier address is incomplete: invalid=' . $field);
            }
            $courier[$field] = $value;
        }

        return $courier;
    }

    /** @param array<mixed> $payload @param array<string,mixed> $shipping @param array<string,mixed> $billing @return array<mixed> */
    public function apply(array $payload, array $shipping, array $billing = []): array
    {
        if (!isset($payload['delivery']['courier']) || !is_array($payload['delivery']['courier'])) {
            return $payload;
        }

        // Checkout uses one billing address unless a separate shipping address was provided.
        $destination = trim((string) ($shipping['address_1'] ?? $shipping['address'] ?? '')) !== '' ? $shipping : $billing;
        $payload['delivery']['courier'] = $this->build($destination, $payload['delivery']['courier']);
        return $payload;
    }
}

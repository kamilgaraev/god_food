<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

final class CdekPackageBuilder
{
    public function __construct(private readonly int $senderCityCode)
    {
    }

    /**
     * @param array{postal_code?:string,city?:string,address?:string} $destination
     * @param list<array{quantity:int,weight_kg:float|int,length_cm?:float|int,width_cm?:float|int,height_cm?:float|int}> $lines
     * @return array<mixed>
     */
    public function build(array $destination, array $lines): array
    {
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;

        foreach ($lines as $line) {
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $lineWeight = (float) ($line['weight_kg'] ?? 0);
            if ($lineWeight <= 0) {
                throw new \InvalidArgumentException('Every shippable product must have a positive weight');
            }

            $weight += (int) round($lineWeight * 1000) * $quantity;
            $length = max($length, (int) ceil((float) ($line['length_cm'] ?? 10)));
            $width = max($width, (int) ceil((float) ($line['width_cm'] ?? 10)));
            $height += max(1, (int) ceil((float) ($line['height_cm'] ?? 1))) * $quantity;
        }

        if ($weight <= 0 || $lines === []) {
            throw new \InvalidArgumentException('A non-empty package with a positive weight is required');
        }

        $to = array_filter([
            'postal_code' => trim((string) ($destination['postal_code'] ?? '')),
            'city' => trim((string) ($destination['city'] ?? '')),
            'address' => trim((string) ($destination['address'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
        if ($to === []) {
            throw new \InvalidArgumentException('A destination is required');
        }

        return [
            'type' => 1,
            'from_location' => ['code' => $this->senderCityCode],
            'to_location' => $to,
            'packages' => [[
                'weight' => $weight,
                'length' => max(1, $length),
                'width' => max(1, $width),
                'height' => max(1, $height),
            ]],
        ];
    }
}

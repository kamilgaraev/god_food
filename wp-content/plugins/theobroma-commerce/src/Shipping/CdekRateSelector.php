<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

final class CdekRateSelector
{
    /** @param list<array<mixed>> $rates
     *  @return array<mixed>|null
     */
    public function cheapest(array $rates, string $kind, string $shipmentPoint = ''): ?array
    {
        $warehouseOrigin = trim($shipmentPoint) !== '';
        $modes = $kind === 'pickup' ? [$warehouseOrigin ? 4 : 2] : [$warehouseOrigin ? 3 : 1];
        $eligible = array_values(array_filter($rates, static function (array $rate) use ($modes): bool {
            $fulfillment = in_array((int) ($rate['tariff_code'] ?? 0), [357, 358], true)
                || preg_match('/фулфилмент|fulfil{1,2}ment/iu', (string) ($rate['tariff_name'] ?? '')) === 1;
            return !$fulfillment && in_array((int) ($rate['delivery_mode'] ?? 0), $modes, true)
                && isset($rate['delivery_sum'])
                && (float) $rate['delivery_sum'] >= 0;
        }));

        usort($eligible, static fn (array $a, array $b): int => (float) $a['delivery_sum'] <=> (float) $b['delivery_sum']);

        return $eligible[0] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

final class CdekRateSelector
{
    /** @param list<array<mixed>> $rates
     *  @return array<mixed>|null
     */
    public function cheapest(array $rates, string $kind): ?array
    {
        $modes = $kind === 'pickup' ? [2, 4] : [1, 3];
        $eligible = array_values(array_filter($rates, static function (array $rate) use ($modes): bool {
            return in_array((int) ($rate['delivery_mode'] ?? 0), $modes, true)
                && isset($rate['delivery_sum'])
                && (float) $rate['delivery_sum'] >= 0;
        }));

        usort($eligible, static fn (array $a, array $b): int => (float) $a['delivery_sum'] <=> (float) $b['delivery_sum']);

        return $eligible[0] ?? null;
    }
}

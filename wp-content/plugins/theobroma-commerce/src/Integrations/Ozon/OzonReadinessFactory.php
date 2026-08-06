<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

use Theobroma\Commerce\Products\OzonCatalogAudit;

final class OzonReadinessFactory
{
    /** @param array<string,mixed> $settings @param iterable<object> $products */
    public function build(array $settings, bool $credentialsConfigured, iterable $products): OzonCapabilities
    {
        $catalog = (new OzonCatalogAudit())->audit($products);

        return new OzonCapabilities(
            ($settings['ozon_approved'] ?? 'no') === 'yes',
            $credentialsConfigured,
            $catalog['complete'],
            ($settings['ozon_products_mapped'] ?? 'no') === 'yes',
            ($settings['ozon_live_test_completed'] ?? 'no') === 'yes'
        );
    }
}

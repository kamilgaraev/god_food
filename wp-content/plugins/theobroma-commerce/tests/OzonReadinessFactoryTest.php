<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonReadinessFactory;

final class OzonReadinessFactoryTest extends TestCase
{
    public function testRequiresActualCatalogMappingInAdditionToAdminConfirmations(): void
    {
        $settings = [
            'ozon_approved' => 'yes',
            'ozon_products_mapped' => 'yes',
            'ozon_live_test_completed' => 'yes',
        ];
        $factory = new OzonReadinessFactory();

        $unmapped = $factory->build($settings, true, [new OzonAuditProduct(21, '')]);
        $mapped = $factory->build($settings, true, [new OzonAuditProduct(21, 'OZ-21')]);

        $this->assertSame('products_unmapped', $unmapped->status());
        $this->assertSame('ready', $mapped->status());
    }
}

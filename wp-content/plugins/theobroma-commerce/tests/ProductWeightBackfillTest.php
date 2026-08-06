<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Catalog\ProductWeightBackfill;

final class ProductWeightBackfillTest extends TestCase
{
    public function testDerivesOnlyUnambiguousCatalogWeightsFromSku(): void
    {
        $migration = new ProductWeightBackfill();

        $this->assertSame(200, $migration->weightFromSku('theobroma-200-68-coriander'));
        $this->assertSame(30, $migration->weightFromSku('theobroma-30-date-powder'));
        $this->assertSame(400, $migration->weightFromSku('theobroma-cacao-400'));
        $this->assertSame(250, $migration->weightFromSku('theobroma-chia-250'));
        $this->assertSame(null, $migration->weightFromSku('other-product-200'));
        $this->assertSame(null, $migration->weightFromSku('theobroma-invalid'));
    }
}

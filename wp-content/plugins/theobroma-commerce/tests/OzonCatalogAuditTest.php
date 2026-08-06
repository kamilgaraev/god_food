<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Products\OzonCatalogAudit;

final class OzonCatalogAuditTest extends TestCase
{
    public function testReportsEveryPublishedProductWithoutAnOzonSku(): void
    {
        $report = (new OzonCatalogAudit())->audit([
            new OzonAuditProduct(11, 'OZ-11'),
            new OzonAuditProduct(12, ''),
            new OzonAuditProduct(13, '  '),
        ]);

        $this->assertSame(3, $report['total']);
        $this->assertSame(1, $report['mapped']);
        $this->assertSame([12, 13], $report['missing_product_ids']);
        $this->assertSame(false, $report['complete']);
    }

    public function testEmptyCatalogCannotBeMarkedAsMapped(): void
    {
        $report = (new OzonCatalogAudit())->audit([]);

        $this->assertSame(0, $report['total']);
        $this->assertSame(false, $report['complete']);
    }
}

final class OzonAuditProduct
{
    public function __construct(
        private readonly int $id,
        private readonly string $ozonSku
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_meta(string $key, bool $single): string
    {
        return $key === '_theobroma_ozon_sku' && $single ? $this->ozonSku : '';
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Shipping\OzonCartEligibility;

final class OzonCartEligibilityTest extends TestCase
{
    public function testAcceptsCartOnlyWhenEveryProductHasOzonSku(): void
    {
        $eligibility = new OzonCartEligibility(static fn (int $id): ?object => null);

        $this->assertSame(true, $eligibility->allItemsMapped([
            'contents' => [
                ['data' => new OzonCartProduct('1001'), 'product_id' => 10, 'variation_id' => 0],
                ['data' => new OzonCartProduct('1002'), 'product_id' => 20, 'variation_id' => 0],
            ],
        ]));
    }

    public function testRejectsWholeCartWhenOneProductHasNoOzonSku(): void
    {
        $eligibility = new OzonCartEligibility(static fn (int $id): ?object => null);

        $this->assertSame(false, $eligibility->allItemsMapped([
            'contents' => [
                ['data' => new OzonCartProduct('1001'), 'product_id' => 10, 'variation_id' => 0],
                ['data' => new OzonCartProduct(''), 'product_id' => 20, 'variation_id' => 0],
            ],
        ]));
    }

    public function testUsesVariationSkuBeforeParentSku(): void
    {
        $parentLoads = 0;
        $eligibility = new OzonCartEligibility(static function (int $id) use (&$parentLoads): object {
            $parentLoads++;
            return new OzonCartProduct('parent-sku');
        });

        $mapped = $eligibility->allItemsMapped([
            'contents' => [
                ['data' => new OzonCartProduct('variation-sku'), 'product_id' => 10, 'variation_id' => 11],
            ],
        ]);

        $this->assertSame(true, $mapped);
        $this->assertSame(0, $parentLoads);
    }

    public function testFallsBackFromVariationToParentSku(): void
    {
        $eligibility = new OzonCartEligibility(static fn (int $id): object => new OzonCartProduct($id === 10 ? 'parent-sku' : ''));

        $this->assertSame(true, $eligibility->allItemsMapped([
            'contents' => [
                ['data' => new OzonCartProduct(''), 'product_id' => 10, 'variation_id' => 11],
            ],
        ]));
    }

    public function testRejectsEmptyOrMalformedCart(): void
    {
        $eligibility = new OzonCartEligibility(static fn (int $id): ?object => null);

        $this->assertSame(false, $eligibility->allItemsMapped(['contents' => []]));
        $this->assertSame(false, $eligibility->allItemsMapped(['contents' => [['product_id' => 10]]]));
    }

    public function testChecksFullCartContentsInsteadOfOnlyCurrentShippingPackage(): void
    {
        $eligibility = new OzonCartEligibility(static fn (int $id): ?object => null);
        $currentPackage = [
            ['data' => new OzonCartProduct('1001'), 'product_id' => 10, 'variation_id' => 0],
        ];
        $fullCart = [
            ...$currentPackage,
            ['data' => new OzonCartProduct(''), 'product_id' => 20, 'variation_id' => 0],
        ];

        $this->assertSame(true, $eligibility->allContentsMapped($currentPackage));
        $this->assertSame(false, $eligibility->allContentsMapped($fullCart));
    }
}

final class OzonCartProduct
{
    public function __construct(private readonly string $sku)
    {
    }

    public function get_meta(string $key, bool $single): string
    {
        return $key === '_theobroma_ozon_sku' && $single ? $this->sku : '';
    }
}

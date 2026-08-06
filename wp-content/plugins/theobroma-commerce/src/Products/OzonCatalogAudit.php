<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Products;

final class OzonCatalogAudit
{
    /**
     * @param iterable<object> $products
     * @return array{total:int,mapped:int,missing_product_ids:list<int>,complete:bool}
     */
    public function audit(iterable $products): array
    {
        $total = 0;
        $mapped = 0;
        $missingProductIds = [];

        foreach ($products as $product) {
            $total++;
            $ozonSku = trim((string) $product->get_meta('_theobroma_ozon_sku', true));
            if ($ozonSku !== '') {
                $mapped++;
                continue;
            }

            $missingProductIds[] = (int) $product->get_id();
        }

        return [
            'total' => $total,
            'mapped' => $mapped,
            'missing_product_ids' => $missingProductIds,
            'complete' => $total > 0 && $mapped === $total,
        ];
    }
}

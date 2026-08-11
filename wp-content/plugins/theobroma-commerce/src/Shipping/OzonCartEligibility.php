<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Shipping;

final class OzonCartEligibility
{
    private readonly \Closure $productLoader;

    public function __construct(?callable $productLoader = null)
    {
        $this->productLoader = $productLoader !== null
            ? \Closure::fromCallable($productLoader)
            : static fn (int $productId): mixed => wc_get_product($productId);
    }

    /** @param array<mixed> $package */
    public function allItemsMapped(array $package): bool
    {
        $contents = $package['contents'] ?? null;
        return is_array($contents) && $this->allContentsMapped($contents);
    }

    /** @param array<mixed> $contents */
    public function allContentsMapped(array $contents): bool
    {
        if ($contents === []) {
            return false;
        }

        foreach ($contents as $item) {
            if (!is_array($item) || !is_object($item['data'] ?? null) || !method_exists($item['data'], 'get_meta')) {
                return false;
            }
            $sku = trim((string) $item['data']->get_meta('_theobroma_ozon_sku', true));
            if ($sku !== '') {
                continue;
            }
            if ((int) ($item['variation_id'] ?? 0) <= 0 || (int) ($item['product_id'] ?? 0) <= 0) {
                return false;
            }
            $parent = ($this->productLoader)((int) $item['product_id']);
            if (!is_object($parent) || !method_exists($parent, 'get_meta')) {
                return false;
            }
            if (trim((string) $parent->get_meta('_theobroma_ozon_sku', true)) === '') {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Catalog;

final class ProductWeightBackfill
{
    private const MIGRATION_OPTION = 'theobroma_commerce_weight_migration';

    public function register(): void
    {
        add_action('init', [$this, 'run'], 20);
    }

    public function run(): void
    {
        if ((int) get_option(self::MIGRATION_OPTION, 0) >= 1 || !function_exists('wc_get_products')) {
            return;
        }

        $unit = (string) get_option('woocommerce_weight_unit', 'kg');
        foreach (wc_get_products(['status' => ['publish', 'draft', 'private'], 'limit' => -1]) as $product) {
            if (!$product instanceof \WC_Product || !$product->needs_shipping() || (float) $product->get_weight() > 0) {
                continue;
            }
            $grams = $this->weightFromSku($product->get_sku());
            if ($grams === null) {
                continue;
            }
            $product->set_weight((string) wc_get_weight($grams, $unit, 'g'));
            $product->save();
        }

        update_option(self::MIGRATION_OPTION, 1, false);
    }

    public function weightFromSku(string $sku): ?int
    {
        if (preg_match('/^theobroma-(?:cacao|chia)-(\d+)$/', $sku, $match)) {
            return (int) $match[1];
        }
        if (preg_match('/^theobroma-(\d+)-[a-z0-9-]+$/', $sku, $match)) {
            return (int) $match[1];
        }
        return null;
    }
}

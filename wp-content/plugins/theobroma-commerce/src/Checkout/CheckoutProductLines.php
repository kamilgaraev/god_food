<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class CheckoutProductLines
{
    /** @var callable(int):mixed */
    private $productLoader;

    public function __construct(?callable $productLoader = null)
    {
        $this->productLoader = $productLoader ?? static fn (int $id): mixed => function_exists('wc_get_product') ? wc_get_product($id) : null;
    }

    /** @param list<array<string,mixed>> $contents @return list<array{quantity:int,sku:int}> */
    public function ozon(array $contents): array
    {
        $result = [];
        foreach ($contents as $item) {
            $product = $item['data'] ?? null;
            if (!is_object($product) || !method_exists($product, 'get_meta')) {
                throw new \InvalidArgumentException('Cart contains an invalid product');
            }
            $ozonSku = trim((string) $product->get_meta('_theobroma_ozon_sku', true));
            if ($ozonSku === '' && (int) ($item['variation_id'] ?? 0) > 0) {
                $parent = ($this->productLoader)((int) ($item['product_id'] ?? 0));
                if (is_object($parent) && method_exists($parent, 'get_meta')) {
                    $ozonSku = trim((string) $parent->get_meta('_theobroma_ozon_sku', true));
                }
            }
            if ($ozonSku === '' || !ctype_digit($ozonSku) || (int) $ozonSku < 1) {
                throw new \InvalidArgumentException('Every cart product must have a positive numeric Ozon SKU');
            }
            $result[] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'sku' => (int) $ozonSku,
            ];
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $contents @return list<array{quantity:int,weight_kg:float,length_cm:float,width_cm:float,height_cm:float}> */
    public function cdek(array $contents): array
    {
        $result = [];
        foreach ($contents as $item) {
            $product = $item['data'] ?? null;
            if (!is_object($product) || !method_exists($product, 'get_weight')) {
                throw new \InvalidArgumentException('Cart contains an invalid product');
            }
            $weight = (float) $product->get_weight();
            $length = method_exists($product, 'get_length') ? (float) $product->get_length() : 0.0;
            $width = method_exists($product, 'get_width') ? (float) $product->get_width() : 0.0;
            $height = method_exists($product, 'get_height') ? (float) $product->get_height() : 0.0;
            if (function_exists('wc_get_weight')) {
                $weight = (float) wc_get_weight($weight, 'kg');
            }
            if (function_exists('wc_get_dimension')) {
                $length = (float) wc_get_dimension($length, 'cm');
                $width = (float) wc_get_dimension($width, 'cm');
                $height = (float) wc_get_dimension($height, 'cm');
            }
            $result[] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'weight_kg' => $weight,
                'length_cm' => $length > 0 ? $length : 10.0,
                'width_cm' => $width > 0 ? $width : 10.0,
                'height_cm' => $height > 0 ? $height : 1.0,
            ];
        }
        return $result;
    }
}

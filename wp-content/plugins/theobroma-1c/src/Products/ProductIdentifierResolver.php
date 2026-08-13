<?php
declare(strict_types=1);
namespace Theobroma\OneC\Products;

final class ProductIdentifierResolver
{
    public function resolve(ProductIdentifiers $identifiers): ResolvedProductIdentifier
    {
        $all = $identifiers->all();
        foreach (['1c_guid', '1c_article', 'client_article', 'woo_sku'] as $type) {
            if (isset($all[$type])) {
                return new ResolvedProductIdentifier($type, $all[$type], $all);
            }
        }
        throw new \InvalidArgumentException('Product has no usable identifier');
    }
}

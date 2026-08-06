<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Wishlist;

final class WishlistStore
{
    public const META_KEY = '_theobroma_wishlist_product_ids';

    /** @param iterable<mixed> $ids @param callable(int):bool $isValid @return list<int> */
    public function normalize(iterable $ids, callable $isValid): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = abs((int) $id);
            if ($id < 1 || isset($normalized[$id]) || !$isValid($id)) {
                continue;
            }
            $normalized[$id] = $id;
            if (count($normalized) >= 100) {
                break;
            }
        }
        return array_values($normalized);
    }
}

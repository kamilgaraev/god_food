<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Wishlist\WishlistStore;

final class WishlistStoreTest extends TestCase
{
    public function testNormalizesUniqueVisibleProductIds(): void
    {
        $actual = (new WishlistStore())->normalize([4, '4', -7, 0, 8, 9], static fn (int $id): bool => in_array($id, [4, 7, 9], true));
        $this->assertSame([4, 7, 9], $actual);
    }

    public function testBoundsWishlistSize(): void
    {
        $actual = (new WishlistStore())->normalize(range(1, 150), static fn (): bool => true);
        $this->assertSame(100, count($actual));
        $this->assertSame(100, $actual[99]);
    }
}

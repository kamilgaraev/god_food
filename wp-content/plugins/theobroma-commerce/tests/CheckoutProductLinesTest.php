<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\CheckoutProductLines;

final class CheckoutProductLinesTest extends TestCase
{
    public function testBuildsProviderLinesFromServerSideProducts(): void
    {
        $product = new CheckoutProductStub(10, 'CHOCO-100', '100500', 7.99, 0.1, 12, 8, 2);
        $lines = new CheckoutProductLines();
        $contents = [['data' => $product, 'quantity' => 2, 'product_id' => 10, 'variation_id' => 0]];

        $this->assertSame([['offer_id' => 'CHOCO-100', 'quantity' => 2, 'sku' => 100500]], $lines->ozon($contents));
        $this->assertSame(0.1, $lines->cdek($contents)[0]['weight_kg']);
        $this->assertSame(12.0, $lines->cdek($contents)[0]['length_cm']);
    }
}

final class CheckoutProductStub
{
    public function __construct(
        private int $id,
        private string $sku,
        private string $ozonSku,
        private float $price,
        private float $weight,
        private float $length,
        private float $width,
        private float $height
    ) {}

    public function get_id(): int { return $this->id; }
    public function get_sku(): string { return $this->sku; }
    public function get_price(): string { return (string) $this->price; }
    public function get_weight(): string { return (string) $this->weight; }
    public function get_length(): string { return (string) $this->length; }
    public function get_width(): string { return (string) $this->width; }
    public function get_height(): string { return (string) $this->height; }
    public function get_meta(string $key, bool $single): string { return $key === '_theobroma_ozon_sku' ? $this->ozonSku : ''; }
}

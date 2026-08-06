<?php

declare(strict_types=1);

namespace Theobroma\Seo\Tests;

use Theobroma\Seo\MetadataRenderer;
use Theobroma\Seo\SeoDocument;

final class MetadataRendererTest extends TestCase
{
    public function testRendersEscapedSocialMetadata(): void
    {
        $document = new SeoDocument(
            title: 'Шоколад <70%>',
            description: 'Натуральный & пористый шоколад',
            canonicalUrl: 'https://example.test/product/chocolate',
            type: 'product',
            siteName: 'Пища Богов',
            imageUrl: 'https://example.test/chocolate.jpg'
        );

        $html = (new MetadataRenderer())->render($document);

        $this->assertContains('<meta name="description" content="Натуральный &amp; пористый шоколад">', $html);
        $this->assertContains('<meta property="og:title" content="Шоколад &lt;70%&gt;">', $html);
        $this->assertContains('<meta property="og:type" content="product">', $html);
        $this->assertContains('<meta property="og:image" content="https://example.test/chocolate.jpg">', $html);
        $this->assertContains('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function testRendersValidProductJsonLd(): void
    {
        $document = new SeoDocument(
            title: '68% горький шоколад 200г',
            description: 'Натуральный шоколад с кориандром.',
            canonicalUrl: 'https://example.test/product/coriander',
            type: 'product',
            siteName: 'Пища Богов',
            imageUrl: 'https://example.test/main.jpg',
            schema: [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => '68% горький шоколад 200г',
                'sku' => 'THEOBROMA-200-68-CORIANDER',
                'image' => ['https://example.test/main.jpg', 'https://example.test/second.jpg'],
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'RUB',
                    'price' => '1426.00',
                    'availability' => 'https://schema.org/InStock',
                    'url' => 'https://example.test/product/coriander',
                ],
            ]
        );

        $html = (new MetadataRenderer())->render($document);

        $this->assertContains('<script type="application/ld+json">', $html);
        $this->assertContains('"@type":"Product"', $html);
        $this->assertContains('"price":"1426.00"', $html);
        $this->assertContains('"availability":"https://schema.org/InStock"', $html);
        $this->assertNotContains('&quot;', $html, 'JSON-LD must be JSON, not HTML entities.');
    }

    public function testOmitsEmptyOptionalImageMetadata(): void
    {
        $document = new SeoDocument(
            title: 'Доставка и оплата',
            description: 'Условия доставки и оплаты заказов.',
            canonicalUrl: 'https://example.test/delivery',
            type: 'website',
            siteName: 'Пища Богов'
        );

        $html = (new MetadataRenderer())->render($document);

        $this->assertNotContains('og:image', $html);
        $this->assertNotContains('application/ld+json', $html);
    }
}

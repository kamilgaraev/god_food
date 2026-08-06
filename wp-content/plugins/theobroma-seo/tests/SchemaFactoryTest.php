<?php

declare(strict_types=1);

namespace Theobroma\Seo\Tests;

use Theobroma\Seo\SchemaFactory;

final class SchemaFactoryTest extends TestCase
{
    public function testBuildsSearchReadyProductSchema(): void
    {
        $schema = (new SchemaFactory())->product([
            'name' => '70% горький шоколад 200г',
            'description' => 'Натуральный пористый шоколад.',
            'url' => 'https://example.test/product/dark-70',
            'sku' => 'CHOCO-70-200',
            'images' => ['https://example.test/one.jpg', 'https://example.test/two.jpg'],
            'price' => '1418.00',
            'currency' => 'RUB',
            'in_stock' => true,
        ]);

        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('Theobroma', $schema['brand']['name']);
        $this->assertSame('1418.00', $schema['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame(['https://example.test/one.jpg', 'https://example.test/two.jpg'], $schema['image']);
    }

    public function testBuildsArticleSchemaWithDatesAndPublisher(): void
    {
        $schema = (new SchemaFactory())->article([
            'headline' => 'Как выбрать настоящий шоколад',
            'description' => 'Разбираемся в составе продукта.',
            'url' => 'https://example.test/media/real-chocolate',
            'image' => 'https://example.test/article.jpg',
            'date_published' => '2026-08-01T10:00:00+03:00',
            'date_modified' => '2026-08-02T12:00:00+03:00',
            'author' => 'Редакция Пища Богов',
            'logo' => 'https://example.test/logo.png',
        ]);

        $this->assertSame('Article', $schema['@type']);
        $this->assertSame('Редакция Пища Богов', $schema['author']['name']);
        $this->assertSame('Пища Богов', $schema['publisher']['name']);
        $this->assertSame('2026-08-02T12:00:00+03:00', $schema['dateModified']);
    }

    public function testBuildsOrganizationAndWebsiteGraph(): void
    {
        $schema = (new SchemaFactory())->site([
            'url' => 'https://example.test/',
            'name' => 'Пища Богов',
            'description' => 'Натуральный пористый шоколад.',
            'logo' => 'https://example.test/logo.png',
        ]);

        $this->assertSame('Organization', $schema['@graph'][0]['@type']);
        $this->assertSame('WebSite', $schema['@graph'][1]['@type']);
        $this->assertSame('https://example.test/#organization', $schema['@graph'][1]['publisher']['@id']);
    }
}

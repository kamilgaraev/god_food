<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

use InvalidArgumentException;
use Theobroma\OneC\Products\ProductIdentifierResolver;
use Theobroma\OneC\Products\ProductIdentifiers;

final class ProductIdentifierResolverTest
{
    public function testUsesStablePriorityAndPreservesAllIdentifiers(): void
    {
        $resolved = (new ProductIdentifierResolver())->resolve(new ProductIdentifiers(
            'woo-1', 'GUID-1', 'ONE-C-1', 'CLIENT-1', '42', '84', '4601234567890'
        ));
        $this->same('GUID-1', $resolved->value);
        $this->same('1c_guid', $resolved->type);
        $this->same('84', $resolved->all['ozon_sku']);
    }

    public function testFallsBackWithoutAssumingClientArticleIsOneCArticle(): void
    {
        $resolver = new ProductIdentifierResolver();
        $article = $resolver->resolve(new ProductIdentifiers('woo-1', '', '', 'CLIENT-1'));
        $woo = $resolver->resolve(new ProductIdentifiers('woo-1'));
        $this->same('client_article', $article->type);
        $this->same('woo_sku', $woo->type);
    }

    public function testRejectsProductWithoutUsableIdentifier(): void
    {
        try {
            (new ProductIdentifierResolver())->resolve(new ProductIdentifiers(''));
        } catch (InvalidArgumentException) {
            return;
        }
        throw new \RuntimeException('Expected empty identifiers to be rejected');
    }

    private function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}

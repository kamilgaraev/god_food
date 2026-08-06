<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/wp-load.php';

use Theobroma\Seo\MetadataRenderer;
use Theobroma\Seo\WordPressDocumentResolver;

if (!class_exists(WordPressDocumentResolver::class)) {
    fwrite(STDERR, "FAIL WordPressDocumentResolver is not loaded\n");
    exit(1);
}

$products = wc_get_products([
    'status' => 'publish',
    'limit' => 1,
    'return' => 'objects',
]);
if ($products === []) {
    fwrite(STDERR, "FAIL catalog has no published product\n");
    exit(1);
}

$resolver = new WordPressDocumentResolver();
$shopDocument = $resolver->forShop();
if ($shopDocument->description === '' || $shopDocument->type !== 'website') {
    fwrite(STDERR, "FAIL shop archive metadata is incomplete\n");
    exit(1);
}

$productDocument = $resolver->forProduct($products[0]);
$productHtml = (new MetadataRenderer())->render($productDocument);
if (!str_contains($productHtml, '<meta name="description"') || !str_contains($productHtml, '"@type":"Product"')) {
    fwrite(STDERR, "FAIL product metadata is incomplete\n");
    exit(1);
}
if ($productDocument->description === '' || $productDocument->imageUrl === '') {
    fwrite(STDERR, "FAIL product metadata lacks description or image\n");
    exit(1);
}

$article = get_posts([
    'post_type' => 'post',
    'post_status' => 'publish',
    'numberposts' => 1,
])[0] ?? null;
if (!$article instanceof WP_Post) {
    fwrite(STDERR, "FAIL media section has no published article\n");
    exit(1);
}

$articleDocument = $resolver->forPost($article);
if (($articleDocument->schema['@type'] ?? '') !== 'Article') {
    fwrite(STDERR, "FAIL article schema is missing\n");
    exit(1);
}

echo "WordPress SEO smoke passed\n";

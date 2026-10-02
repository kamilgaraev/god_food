<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/wp-load.php';

use Theobroma\Seo\MetadataRenderer;
use Theobroma\Seo\Plugin;
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

$publishedProducts = wc_get_products(['status' => 'publish', 'limit' => -1]);
$titles = array_map(
    static fn (\WC_Product $product): string => $resolver->productTitle($product),
    $publishedProducts
);
if (count($titles) !== count(array_unique($titles))) {
    fwrite(STDERR, "FAIL product SEO titles are not unique\n");
    exit(1);
}

$descriptions = array_map(
    static fn (\WC_Product $product): string => $resolver->forProduct($product)->description,
    $publishedProducts
);
foreach (get_posts(['post_type' => 'theobroma_recipe', 'post_status' => 'publish', 'numberposts' => -1]) as $recipe) {
    $descriptions[] = $resolver->forPost($recipe)->description;
}
if (count($descriptions) !== count(array_unique($descriptions))) {
    fwrite(STDERR, "FAIL product or recipe SEO descriptions are not unique\n");
    exit(1);
}

$terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 1]);
if (is_array($terms) && ($terms[0] ?? null) instanceof WP_Term) {
    $categoryDocument = $resolver->forProductCategory($terms[0]);
    if ($categoryDocument->description === '' || $categoryDocument->canonicalUrl === '') {
        fwrite(STDERR, "FAIL product category metadata is incomplete\n");
        exit(1);
    }
}

$sitemapArgs = Plugin::sitemapPosts([], 'page');
foreach (['sample-page', 'policy-2', 'marketplace', 'offer'] as $slug) {
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post && !in_array($page->ID, $sitemapArgs['post__not_in'], true)) {
        fwrite(STDERR, "FAIL redirect page remains in sitemap: {$slug}\n");
        exit(1);
    }
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

$portraitArticle = get_page_by_path('kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka', OBJECT, 'post');
$portraitMetadata = $portraitArticle instanceof WP_Post
    ? wp_get_attachment_metadata((int) get_post_thumbnail_id($portraitArticle))
    : false;
if ($portraitArticle instanceof WP_Post
    && get_post_meta($portraitArticle->ID, '_theobroma_seo_og_image', true) === ''
    && is_array($portraitMetadata)
    && (int) ($portraitMetadata['height'] ?? 0) > (int) ($portraitMetadata['width'] ?? 0)) {
    $portraitDocument = $resolver->forPost($portraitArticle);
    $portraitHtml = (new MetadataRenderer())->render($portraitDocument);
    $expectedPreview = get_option('theobroma_seo_social_image', '') ?: get_theme_file_uri('assets/images/social-preview.jpg');
    if ($portraitDocument->imageUrl !== $expectedPreview
        || !str_contains($portraitHtml, 'property="og:image:width" content="1200"')
        || !str_contains($portraitHtml, 'property="og:image:height" content="630"')) {
        fwrite(STDERR, "FAIL portrait article lacks a wide social preview\n");
        exit(1);
    }
}

echo "WordPress SEO smoke passed\n";

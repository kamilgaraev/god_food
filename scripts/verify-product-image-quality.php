<?php
declare(strict_types=1);

$helper = dirname(__DIR__) . '/wp-content/themes/theobroma/inc/product-images.php';
$failures = array();

if (!is_file($helper)) {
    $failures[] = 'Product image helper is missing.';
} else {
    require_once $helper;

    function wp_get_original_image_url(int $id): string { return 'https://example.test/uploads/photo.png'; }
    function wp_get_attachment_image_url(int $id, string $size): string { return wp_get_original_image_url($id); }
    function wp_get_attachment_url(int $id): string { return wp_get_original_image_url($id); }
    function wp_get_original_image_path(int $id): bool { return false; }
    function wp_get_attachment_metadata(int $id): array {
        return array('width' => 1121, 'height' => 1403, 'sizes' => array(
            'cropped-detail' => array('width' => 560, 'height' => 745, 'file' => 'photo-560x745.png'),
            'large' => array('width' => 818, 'height' => 1024, 'file' => 'photo-818x1024.png'),
            'medium' => array('width' => 240, 'height' => 300, 'file' => 'photo-240x300.png'),
        ));
    }
    function trailingslashit(string $path): string { return rtrim($path, '/') . '/'; }
    function esc_url(string $url): string { return $url; }
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    $detail = theobroma_product_detail_image(341, 'Молочный шоколад');
    if (!str_contains($detail, 'photo.png 1121w') || !str_contains($detail, 'data-product-original-image="https://example.test/uploads/photo.png"')) {
        $failures[] = 'Retina display and zoom must have access to the uploaded original.';
    }
    if (str_contains($detail, 'photo-560x745.png') || !str_contains($detail, 'photo-818x1024.png 818w')) {
        $failures[] = 'Only proportional derivatives may be used on the detail page.';
    }

    $optimized = 'https://optim.tildacdn.com/stor6433-6632-4433-a439-363632396661/-/cover/312x390/center/center/-/format/webp/01b928381129561f2b0ec499f692ea63.jpg.webp';
    $original = 'https://static.tildacdn.com/stor6433-6632-4433-a439-363632396661/01b928381129561f2b0ec499f692ea63.jpg';
    if (theobroma_tilda_original_image_url($optimized) !== $original) {
        $failures[] = 'Optimized Tilda URLs must resolve to the original static asset.';
    }
    if (theobroma_tilda_original_image_url($original) !== $original) {
        $failures[] = 'Original Tilda URLs must remain unchanged.';
    }

    if (!theobroma_product_image_needs_upgrade(array('width' => 312, 'height' => 390))) {
        $failures[] = 'Catalogue-sized imports must be upgraded.';
    }
    if (!theobroma_product_image_needs_upgrade(array('width' => 560, 'height' => 745))) {
        $failures[] = 'Detail-sized imports must be upgraded.';
    }
    if (theobroma_product_image_needs_upgrade(array('width' => 1400, 'height' => 1750))) {
        $failures[] = 'Full portrait originals must not be downloaded again.';
    }
    if (theobroma_product_image_needs_upgrade(array('width' => 1600, 'height' => 1600))) {
        $failures[] = 'Full square originals must not be downloaded again.';
    }

    $sizes = theobroma_product_image_sizes();
    $expected_sizes = array(
        'theobroma-product-card' => array(312, 390, true),
        'theobroma-product-card-2x' => array(624, 780, true),
        'theobroma-product-detail' => array(560, 745, false),
        'theobroma-product-detail-2x' => array(1120, 1490, false),
    );
    if ($sizes !== $expected_sizes) {
        $failures[] = 'Product image sizes must preserve the 4:5 catalogue crop and provide 2x variants.';
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", array_map(static fn(string $failure): string => '- ' . $failure, $failures)) . "\n");
    exit(1);
}

echo "Product image quality checks passed.\n";

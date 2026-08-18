<?php
declare(strict_types=1);

$helper = dirname(__DIR__) . '/wp-content/themes/theobroma/inc/product-images.php';
$failures = array();

if (!is_file($helper)) {
    $failures[] = 'Product image helper is missing.';
} else {
    require_once $helper;

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
        'theobroma-product-detail' => array(560, 745, true),
        'theobroma-product-detail-2x' => array(1120, 1490, true),
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

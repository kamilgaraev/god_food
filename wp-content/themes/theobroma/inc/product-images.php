<?php
declare(strict_types=1);

/** Return the untransformed Tilda asset behind an optimized CDN URL. */
function theobroma_tilda_original_image_url(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['host'] ?? '')) !== 'optim.tildacdn.com') {
        return $url;
    }

    $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/')), 'strlen'));
    if (count($segments) < 2 || !preg_match('/^stor[a-z0-9-]+$/i', $segments[0])) {
        return $url;
    }

    $filename = (string) end($segments);
    $filename = preg_replace('/\.(?:webp|avif)$/i', '', $filename) ?? $filename;
    if (!preg_match('/\.(?:jpe?g|png)$/i', $filename)) {
        return $url;
    }

    return 'https://static.tildacdn.com/' . $segments[0] . '/' . $filename;
}

/**
 * @param array<string, mixed>|false $metadata
 */
function theobroma_product_image_needs_upgrade($metadata): bool {
    if (!is_array($metadata)) {
        return true;
    }

    return (int) ($metadata['width'] ?? 0) < 1200 || (int) ($metadata['height'] ?? 0) < 1200;
}

/**
 * @return array<string, array{0:int, 1:int, 2:bool}>
 */
function theobroma_product_image_sizes(): array {
    return array(
        'theobroma-product-card' => array(312, 390, true),
        'theobroma-product-card-2x' => array(624, 780, true),
        'theobroma-product-detail' => array(560, 745, true),
        'theobroma-product-detail-2x' => array(1120, 1490, true),
    );
}

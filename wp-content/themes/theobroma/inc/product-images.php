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
        'theobroma-product-detail' => array(560, 745, false),
        'theobroma-product-detail-2x' => array(1120, 1490, false),
    );
}

/** Detail images keep the entire uploaded photo; the lightbox always uses its original. */
function theobroma_product_detail_image(int $attachment_id, string $alt): string {
    $original = wp_get_original_image_url($attachment_id) ?: wp_get_attachment_image_url($attachment_id, 'full');
    $metadata = wp_get_attachment_metadata($attachment_id) ?: array();
    $path = wp_get_original_image_path($attachment_id);
    $dimensions = $path && is_file($path) ? wp_getimagesize($path) : false;
    $width = (int) ($dimensions[0] ?? $metadata['width'] ?? 0);
    $height = (int) ($dimensions[1] ?? $metadata['height'] ?? 0);
    if (!$original || !$width || !$height) {
        return wp_get_attachment_image($attachment_id, 'full', false, array('data-product-main-image' => '', 'alt' => $alt));
    }
    $sources = array($width => $original);
    $directory = trailingslashit(dirname(wp_get_attachment_url($attachment_id)));
    foreach ($metadata['sizes'] ?? array() as $size) {
        $candidate_width = (int) ($size['width'] ?? 0);
        $candidate_height = (int) ($size['height'] ?? 0);
        // Existing cropped thumbnails must never stretch or cut a detail photo.
        if ($candidate_width && $candidate_height && $candidate_width < $width
            && abs(($candidate_width / $candidate_height) / ($width / $height) - 1) < .01) {
            $sources[$candidate_width] = $directory . $size['file'];
        }
    }
    ksort($sources);
    $src = $original;
    foreach ($sources as $candidate_width => $url) {
        if ($candidate_width >= 560) {
            $src = $url;
            break;
        }
    }
    $srcset = array();
    foreach ($sources as $candidate_width => $url) {
        $srcset[] = esc_url($url) . ' ' . $candidate_width . 'w';
    }
    return sprintf('<img data-product-main-image data-product-original-image="%s" src="%s" srcset="%s" sizes="(max-width: 767px) calc(100vw - 48px), (max-width: 1199px) 48vw, 560px" width="%d" height="%d" alt="%s" decoding="async" fetchpriority="high">',
        esc_url($original), esc_url($src), esc_attr(implode(', ', $srcset)), $width, $height, esc_attr($alt));
}

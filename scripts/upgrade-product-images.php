<?php
declare(strict_types=1);

// Run in the WordPress container; preview by default, --apply to update image references.
require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$apply = in_array('--apply', $argv ?? [], true);
$upgraded = [];
$failures = 0;
foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']]) as $product) {
    $featured = (int) $product->get_image_id();
    $detail = (int) $product->get_meta('_theobroma_product_detail_image_id', true);
    $gallery = $product->get_gallery_image_ids();
    $ids = array_unique(array_filter(array_merge([$featured, $detail], $gallery)));
    $replacements = [];
    foreach ($ids as $id) {
        $metadata = wp_get_attachment_metadata($id);
        if (!theobroma_product_image_needs_upgrade($metadata)) continue;
        $source = (string) get_post_meta($id, '_source_url', true);
        $original = theobroma_tilda_original_image_url($source);
        if ($original === $source || parse_url($original, PHP_URL_HOST) !== 'static.tildacdn.com') continue;
        if (isset($upgraded[$id])) { $replacements[$id] = $upgraded[$id]; continue; }
        echo $product->get_id() . ' image ' . $id . ' ' . ($metadata['width'] ?? 0) . 'x' . ($metadata['height'] ?? 0) . ' -> ' . $original . PHP_EOL;
        if (!$apply) continue;
        $temporary = download_url($original, 30);
        if (is_wp_error($temporary)) { ++$failures; echo 'FAILED download: ' . $temporary->get_error_message() . PHP_EOL; continue; }
        $dimensions = wp_getimagesize($temporary);
        if (!$dimensions || $dimensions[0] <= (int) ($metadata['width'] ?? 0) || $dimensions[1] <= (int) ($metadata['height'] ?? 0)) {
            unlink($temporary);
            echo 'SKIPPED: original has no additional resolution' . PHP_EOL;
            continue;
        }
        $newId = media_handle_sideload(['name' => basename((string) parse_url($original, PHP_URL_PATH)), 'tmp_name' => $temporary], $product->get_id(), $product->get_name());
        if (is_wp_error($newId)) { if (file_exists($temporary)) unlink($temporary); ++$failures; echo 'FAILED import: ' . $newId->get_error_message() . PHP_EOL; continue; }
        update_post_meta($newId, '_source_url', $original);
        update_post_meta($newId, '_wp_attachment_image_alt', get_post_meta($id, '_wp_attachment_image_alt', true) ?: $product->get_name());
        $upgraded[$id] = $replacements[$id] = (int) $newId;
        echo 'IMPORTED ' . $newId . ' ' . $dimensions[0] . 'x' . $dimensions[1] . PHP_EOL;
    }
    if ($apply && $replacements) {
        // Keep prior attachments and references for rollback.
        $product->add_meta_data('_theobroma_image_upgrade_backup', ['featured' => $featured, 'detail' => $detail, 'gallery' => $gallery]);
        if (isset($replacements[$featured])) $product->set_image_id($replacements[$featured]);
        if (isset($replacements[$detail])) $product->update_meta_data('_theobroma_product_detail_image_id', $replacements[$detail]);
        $product->set_gallery_image_ids(array_map(static fn($id) => $replacements[$id] ?? $id, $gallery));
        $product->save();
    }
}
echo 'Upgraded: ' . count($upgraded) . '; failures: ' . $failures . PHP_EOL;
exit($failures ? 1 : 0);

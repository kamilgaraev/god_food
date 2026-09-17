<?php
/** One-time gallery update. Run with PHP CLI inside the WordPress container. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$marker = 'theobroma_corporate_figma_gallery_20260917';
if (get_option($marker)) {
    echo "Gallery already imported; subsequent administrator edits preserved.\n";
    exit;
}
$option = 'theobroma_photo_showcases';
$settings = get_option($option, array());
if (!is_array($settings)) { throw new RuntimeException('Unexpected photo settings'); }
add_option($marker . '_backup', $settings, '', false);
$uploads = wp_upload_dir();
if ($uploads['error']) { throw new RuntimeException($uploads['error']); }
$rows = array();
add_filter('big_image_size_threshold', '__return_false');
foreach (array(
    'gallery-chocolate-original.jpg' => 'Кусочки шоколада Theobroma',
    'gallery-packaging-original.jpg' => 'Подарочная коллекция Theobroma на постаменте',
) as $name => $alt) {
    $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1,
        'meta_key' => '_theobroma_corporate_figma_source', 'meta_value' => $name, 'fields' => 'ids'));
    $id = $existing[0] ?? 0;
    if (!$id) {
        $source = get_template_directory() . '/assets/images/corporate/' . $name;
        $destination = $uploads['path'] . '/' . wp_unique_filename($uploads['path'], $name);
        if (!copy($source, $destination) || hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
            throw new RuntimeException('Original image copy failed: ' . $name);
        }
        $id = wp_insert_attachment(array('post_mime_type' => 'image/jpeg', 'post_title' => $alt, 'post_status' => 'inherit'), $destination, 0, true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $destination));
        update_post_meta($id, '_wp_attachment_image_alt', $alt);
        update_post_meta($id, '_theobroma_corporate_figma_source', $name);
    }
    $rows[] = array('attachment_id' => $id, 'alt' => $alt, 'caption' => '');
}
$home = $settings['home'] ?? null;
$settings['corporate'] = array('enabled' => true, 'title' => '', 'description' => '', 'images' => $rows);
update_option($option, $settings);
$saved = get_option($option);
if ($saved['corporate'] !== $settings['corporate'] || ($saved['home'] ?? null) !== $home) {
    throw new RuntimeException('Photo settings verification failed');
}
add_option($marker, true, '', false);
echo "Two original Figma photographs imported; home gallery preserved.\n";

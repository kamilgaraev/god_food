<?php
/**
 * Plugin Name: Theobroma Photo Showcases Loader
 * Description: Автоматически активирует управляемые фотоподборки Theobroma.
 * Version: 1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// WP-CLI --skip-plugins filters active_plugins; auto-activation would save that
// filtered list and unintentionally deactivate the site's other plugins.
if (defined('WP_CLI') && WP_CLI) {
    return;
}

$theobroma_photo_showcases_plugin = 'theobroma-photo-showcases/theobroma-photo-showcases.php';
$theobroma_photo_showcases_file = WP_PLUGIN_DIR . '/' . $theobroma_photo_showcases_plugin;

if (!is_readable($theobroma_photo_showcases_file)) {
    return;
}

if (!function_exists('is_plugin_active') || !function_exists('activate_plugin')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (is_plugin_active($theobroma_photo_showcases_plugin)) {
    return;
}

$theobroma_photo_showcases_result = activate_plugin($theobroma_photo_showcases_plugin);
if (is_wp_error($theobroma_photo_showcases_result)) {
    error_log(
        '[Theobroma Photo Showcases] Auto-activation failed: '
        . $theobroma_photo_showcases_result->get_error_message()
    );
}

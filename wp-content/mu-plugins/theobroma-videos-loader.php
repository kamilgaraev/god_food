<?php
/** Plugin Name: Theobroma Videos Loader */
defined('ABSPATH') || exit;
$theobroma_videos_file = WP_PLUGIN_DIR . '/theobroma-videos/theobroma-videos.php';
if (is_readable($theobroma_videos_file)) {
    require_once $theobroma_videos_file;
}

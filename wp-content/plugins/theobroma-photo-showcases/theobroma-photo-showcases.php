<?php
/**
 * Plugin Name: Theobroma Photo Showcases
 * Description: Управляемые фотоподборки для Главной и корпоративных подарков Theobroma.
 * Version: 1.1.0
 * Requires PHP: 8.1
 * Text Domain: theobroma-photo-showcases
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('THEOBROMA_PHOTO_SHOWCASES_FILE', __FILE__);
define('THEOBROMA_PHOTO_SHOWCASES_VERSION', '1.1.0');

require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/DefaultImages.php';
require_once __DIR__ . '/src/Renderer.php';
require_once __DIR__ . '/src/AdminPage.php';
require_once __DIR__ . '/src/Plugin.php';

\Theobroma\PhotoShowcases\Plugin::boot();

function theobroma_photo_showcase_html(string $location): string
{
    return \Theobroma\PhotoShowcases\Plugin::instance()->html($location);
}

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$loader = $root . '/wp-content/mu-plugins/theobroma-photo-showcases-loader.php';
if (!is_file($loader)) {
    fwrite(STDERR, "Photo showcases MU loader is missing.\n");
    exit(1);
}

$GLOBALS['theobroma_photo_showcases_activations'] = array();
$GLOBALS['theobroma_photo_showcases_actions'] = array();
$GLOBALS['theobroma_photo_showcases_active'] = false;

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['theobroma_photo_showcases_actions'][$hook][] = $callback;
}

function is_admin(): bool
{
    return false;
}

function is_plugin_active(string $plugin): bool
{
    return $GLOBALS['theobroma_photo_showcases_active'];
}

function activate_plugin(
    string $plugin,
    string $redirect = '',
    bool $networkWide = false,
    bool $silent = false
): mixed {
    $GLOBALS['theobroma_photo_showcases_activations'][] = array($plugin, $networkWide, $silent);
    require_once WP_PLUGIN_DIR . '/' . $plugin;
    $GLOBALS['theobroma_photo_showcases_active'] = true;

    return null;
}

function is_wp_error(mixed $value): bool
{
    return false;
}

defined('ABSPATH') || define('ABSPATH', $root . '/');
defined('WP_PLUGIN_DIR') || define('WP_PLUGIN_DIR', $root . '/wp-content/plugins');

require $loader;

$expectedPlugin = 'theobroma-photo-showcases/theobroma-photo-showcases.php';
$activations = $GLOBALS['theobroma_photo_showcases_activations'];
if (count($activations) !== 1 || $activations[0] !== array($expectedPlugin, false, false)) {
    fwrite(STDERR, "MU loader did not activate the photo showcases plugin exactly once.\n");
    exit(1);
}
if (!function_exists('theobroma_photo_showcase_html')) {
    fwrite(STDERR, "MU loader did not expose the photo showcases API.\n");
    exit(1);
}

require $loader;
if (count($GLOBALS['theobroma_photo_showcases_activations']) !== 1) {
    fwrite(STDERR, "MU loader attempted to activate an already active plugin.\n");
    exit(1);
}

echo "Photo showcases plugin is activated automatically and only once.\n";

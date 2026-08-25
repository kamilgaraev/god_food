<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$loader = $root . '/wp-content/mu-plugins/theobroma-contact-forms-loader.php';
if (!is_file($loader)) {
    fwrite(STDERR, "Contact forms MU loader is missing.\n");
    exit(1);
}

$GLOBALS['theobroma_loader_actions'] = array();
function add_action(string $hook, callable $callback): void {
    $GLOBALS['theobroma_loader_actions'][$hook][] = $callback;
}
function get_option(string $name, mixed $default = false): mixed {
    return $name === 'admin_email' ? 'owner@example.test' : $default;
}
function sanitize_email(string $email): string {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
}

defined('ABSPATH') || define('ABSPATH', $root . '/');
defined('WP_PLUGIN_DIR') || define('WP_PLUGIN_DIR', $root . '/wp-content/plugins');

require $loader;

if (!function_exists('theobroma_contact_forms_render_fields')) {
    fwrite(STDERR, "MU loader did not expose the contact forms API.\n");
    exit(1);
}
if (empty($GLOBALS['theobroma_loader_actions']['admin_menu']) || empty($GLOBALS['theobroma_loader_actions']['admin_init'])) {
    fwrite(STDERR, "MU loader did not register the contact forms settings page.\n");
    exit(1);
}

echo "Contact forms MU loader registers the settings page automatically.\n";

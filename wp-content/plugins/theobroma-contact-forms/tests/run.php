<?php

declare(strict_types=1);

use Theobroma\ContactForms\Settings;
use Theobroma\ContactForms\SettingsPage;
use Theobroma\ContactForms\Submission;
use Theobroma\ContactForms\FieldRenderer;

$GLOBALS['theobroma_test_actions'] = array();
$GLOBALS['theobroma_test_settings'] = array();
$GLOBALS['theobroma_test_menus'] = array();
$GLOBALS['theobroma_test_options'] = array('admin_email' => 'owner@example.test');

function add_action(string $hook, callable $callback): void {
    $GLOBALS['theobroma_test_actions'][$hook][] = $callback;
}
function register_setting(string $group, string $option, array $args): void {
    $GLOBALS['theobroma_test_settings'][$option] = array('group' => $group, 'args' => $args);
}
function add_options_page(string $pageTitle, string $menuTitle, string $capability, string $slug, callable $callback): void {
    $GLOBALS['theobroma_test_menus'][$slug] = compact('pageTitle', 'menuTitle', 'capability', 'callback');
}
function get_option(string $name, mixed $default = false): mixed {
    return $GLOBALS['theobroma_test_options'][$name] ?? $default;
}
function sanitize_email(string $email): string {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
}
function is_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$plugin = dirname(__DIR__);
$required = array(
    $plugin . '/src/Settings.php',
    $plugin . '/src/Submission.php',
    $plugin . '/src/FieldRenderer.php',
    $plugin . '/src/SettingsPage.php',
    $plugin . '/src/Plugin.php',
);
foreach ($required as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, basename($file) . " is missing.\n");
        exit(1);
    }
    require_once $file;
}

$failures = array();
$same = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
};

$settings = new Settings();
$defaults = $settings->defaults('owner@example.test');
foreach (array('home', 'cooperation') as $formId) {
    $same('owner@example.test', $defaults[$formId]['recipient'] ?? null, $formId . ' default recipient');
    $same(true, $defaults[$formId]['fields']['name']['enabled'] ?? null, $formId . ' name enabled');
    $same(true, $defaults[$formId]['fields']['phone']['required'] ?? null, $formId . ' phone required');
    $same(false, $defaults[$formId]['fields']['email']['enabled'] ?? null, $formId . ' email disabled');
    $same(true, $defaults[$formId]['fields']['message']['enabled'] ?? null, $formId . ' message enabled');
}

$sanitized = $settings->sanitize(array(
    'home' => array(
        'recipient' => 'not-an-email',
        'fields' => array(
            'name' => array('enabled' => '0', 'required' => '1'),
            'phone' => array('enabled' => '0', 'required' => '0'),
            'email' => array('enabled' => '1', 'required' => '1'),
            'message' => array('enabled' => '0', 'required' => '0'),
            'unexpected' => array('enabled' => '1', 'required' => '1'),
        ),
    ),
), 'owner@example.test');
$same('owner@example.test', $sanitized['home']['recipient'] ?? null, 'invalid recipient falls back');
$same(true, $sanitized['home']['fields']['name']['enabled'] ?? null, 'required field stays enabled');
$same(true, $sanitized['home']['fields']['email']['required'] ?? null, 'email required is preserved');
$same(false, isset($sanitized['home']['fields']['unexpected']), 'unknown field is dropped');
$same($defaults['cooperation'], $sanitized['cooperation'] ?? null, 'missing form uses defaults');

$submission = new Submission();
$definition = $sanitized['home'];
$same(false, $submission->isValid(array('name' => '', 'email' => ''), $definition), 'required fields reject empty submission');
$same(false, $submission->isValid(array('name' => 'Анна', 'email' => 'bad'), $definition), 'invalid configured email is rejected');
$same(true, $submission->isValid(array('name' => 'Анна', 'email' => 'anna@example.test'), $definition), 'configured required fields accept valid values');
$same(
    array('Имя: Анна', 'E-mail: anna@example.test'),
    $submission->lines(array(
        'name' => 'Анна',
        'phone' => '+7 999 111-22-33',
        'email' => 'anna@example.test',
        'message' => 'Скрытое сообщение',
    ), $definition),
    'notification contains only enabled non-empty fields'
);

$renderer = new FieldRenderer();
$defaultHtml = $renderer->render($defaults['home']);
$same(true, str_contains($defaultHtml, 'name="name"'), 'renderer includes name');
$same(true, str_contains($defaultHtml, 'name="phone"'), 'renderer includes phone');
$same(true, str_contains($defaultHtml, 'name="message"'), 'renderer includes message');
$same(false, str_contains($defaultHtml, 'name="email"'), 'renderer omits disabled email');
$same(true, str_contains($defaultHtml, 'name="phone"') && preg_match('/name="phone"[^>]*required/', $defaultHtml) === 1, 'renderer marks required phone');

$settingsPage = new SettingsPage($settings);
$settingsPage->register();
$settingsPage->settings();
$settingsPage->menu();
$same(true, isset($GLOBALS['theobroma_test_actions']['admin_menu']), 'settings page registers admin menu hook');
$same('array', $GLOBALS['theobroma_test_settings'][Settings::OPTION]['args']['type'] ?? null, 'settings option is registered as array');
$same('manage_options', $GLOBALS['theobroma_test_menus']['theobroma-contact-forms']['capability'] ?? null, 'settings page requires manage_options');

$entry = $plugin . '/theobroma-contact-forms.php';
if (!is_file($entry)) {
    $failures[] = 'plugin entry file is missing';
} else {
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin . '/');
    }
    require_once $entry;
    $same(true, function_exists('theobroma_contact_forms_definition'), 'definition API is available');
    $same(true, function_exists('theobroma_contact_forms_render_fields'), 'renderer API is available');
    $same(true, function_exists('theobroma_contact_forms_validate'), 'validation API is available');
    $same(true, function_exists('theobroma_contact_forms_recipient'), 'recipient API is available');
    $same(true, function_exists('theobroma_contact_forms_notification_lines'), 'notification API is available');
}

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contact forms settings and submission model verified.\n";

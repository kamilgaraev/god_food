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
$GLOBALS['theobroma_test_scripts'] = array();
$GLOBALS['theobroma_test_styles'] = array();

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
function plugins_url(string $path, string $plugin): string {
    return 'https://example.test/plugins/theobroma-contact-forms/' . ltrim($path, '/');
}
function wp_enqueue_script(string $handle, string $src, array $dependencies = array(), string|bool|null $version = false, array|bool $args = array()): void {
    $GLOBALS['theobroma_test_scripts'][$handle] = compact('src', 'dependencies', 'version', 'args');
}
function wp_enqueue_style(string $handle, string $src, array $dependencies = array(), string|bool|null $version = false): void {
    $GLOBALS['theobroma_test_styles'][$handle] = compact('src', 'dependencies', 'version');
}
function current_user_can(string $capability): bool { return $capability === 'manage_options'; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr(string $value): string { return esc_html($value); }
function settings_fields(string $group): void {}
function checked(mixed $checked, mixed $current = true, bool $display = true): string {
    $result = $checked == $current ? 'checked="checked"' : '';
    if ($display) echo $result;
    return $result;
}
function selected(mixed $selected, mixed $current = true, bool $display = true): string {
    $result = $selected == $current ? 'selected="selected"' : '';
    if ($display) echo $result;
    return $result;
}
function submit_button(string $text): void { echo '<button type="submit">' . esc_html($text) . '</button>'; }

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
    $same(array(), $defaults[$formId]['custom_fields'] ?? null, $formId . ' has no custom fields by default');
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
        'custom_fields' => array(
            array(
                'key' => 'Delivery Date!',
                'label' => '  Дата доставки  ',
                'type' => 'text',
                'placeholder' => ' Когда доставить? ',
                'required' => '1',
                'options' => '',
            ),
            array(
                'key' => 'delivery-date',
                'label' => 'Город',
                'type' => 'select',
                'placeholder' => 'Выберите город',
                'required' => '0',
                'options' => "Москва\nСанкт-Петербург\nМосква",
            ),
            array(
                'key' => 'ignored',
                'label' => '',
                'type' => 'script',
            ),
        ),
    ),
), 'owner@example.test');
$same('owner@example.test', $sanitized['home']['recipient'] ?? null, 'invalid recipient falls back');
$same(true, $sanitized['home']['fields']['name']['enabled'] ?? null, 'required field stays enabled');
$same(true, $sanitized['home']['fields']['email']['required'] ?? null, 'email required is preserved');
$same(false, isset($sanitized['home']['fields']['unexpected']), 'unknown field is dropped');
$same('delivery-date', $sanitized['home']['custom_fields'][0]['key'] ?? null, 'custom field key is sanitized');
$same('Дата доставки', $sanitized['home']['custom_fields'][0]['label'] ?? null, 'custom field label is trimmed');
$same(true, $sanitized['home']['custom_fields'][0]['required'] ?? null, 'custom field required flag is preserved');
$same('delivery-date-2', $sanitized['home']['custom_fields'][1]['key'] ?? null, 'duplicate custom field key is made unique');
$same(array('Москва', 'Санкт-Петербург'), $sanitized['home']['custom_fields'][1]['options'] ?? null, 'select options are normalized and deduplicated');
$same(2, count($sanitized['home']['custom_fields'] ?? array()), 'custom fields without labels are dropped');
$same($defaults['cooperation'], $sanitized['cooperation'] ?? null, 'missing form uses defaults');

$submission = new Submission();
$definition = $sanitized['home'];
$same(false, $submission->isValid(array('name' => '', 'email' => ''), $definition), 'required fields reject empty submission');
$same(false, $submission->isValid(array('name' => 'Анна', 'email' => 'bad'), $definition), 'invalid configured email is rejected');
$same(true, $submission->isValid(array('name' => 'Анна', 'email' => 'anna@example.test', 'custom' => array('delivery-date' => 'Завтра')), $definition), 'configured required fields accept valid values');
$same(false, $submission->isValid(array('name' => 'Анна', 'email' => 'anna@example.test', 'custom' => array()), $definition), 'required custom field rejects empty value');
$same(false, $submission->isValid(array('name' => 'Анна', 'email' => 'anna@example.test', 'custom' => array('delivery-date' => 'Завтра', 'delivery-date-2' => 'Казань')), $definition), 'custom select rejects an unknown option');
$same(true, $submission->isValid(array('name' => 'Анна', 'email' => 'anna@example.test', 'custom' => array('delivery-date' => 'Завтра', 'delivery-date-2' => 'Москва')), $definition), 'configured custom fields accept valid values');
$typedDefinition = array(
    'fields' => array(),
    'custom_fields' => array(
        array('key' => 'reply-email', 'label' => 'Почта для ответа', 'type' => 'email', 'required' => true, 'options' => array()),
        array('key' => 'quantity', 'label' => 'Количество', 'type' => 'number', 'required' => false, 'options' => array()),
    ),
);
$same(false, $submission->isValid(array('custom' => array('reply-email' => 'bad', 'quantity' => '10')), $typedDefinition), 'custom email validates its type');
$same(false, $submission->isValid(array('custom' => array('reply-email' => 'reply@example.test', 'quantity' => 'ten')), $typedDefinition), 'custom number validates its type');
$same(true, $submission->isValid(array('custom' => array('reply-email' => 'reply@example.test', 'quantity' => '10')), $typedDefinition), 'typed custom fields accept valid values');
$same(
    array('Имя: Анна', 'E-mail: anna@example.test', 'Дата доставки: Завтра', 'Город: Москва'),
    $submission->lines(array(
        'name' => 'Анна',
        'phone' => '+7 999 111-22-33',
        'email' => 'anna@example.test',
        'message' => 'Скрытое сообщение',
        'custom' => array(
            'delivery-date' => 'Завтра',
            'delivery-date-2' => 'Москва',
            'forged-field' => 'Не должно попасть в письмо',
        ),
    ), $definition),
    'notification contains only enabled non-empty fields'
);
if (!method_exists($submission, 'values')) {
    $failures[] = 'Submission::values is missing';
} else {
    $same(
        array('name' => 'Анна', 'email' => 'anna@example.test', 'custom_delivery-date' => 'Завтра', 'custom_delivery-date-2' => 'Москва'),
        $submission->values(array(
            'name' => 'Анна',
            'phone' => '+7 999 111-22-33',
            'email' => 'anna@example.test',
            'message' => 'Скрытое сообщение',
            'custom' => array(
                'delivery-date' => 'Завтра',
                'delivery-date-2' => 'Москва',
                'forged-field' => 'Не должно сохраниться',
            ),
        ), $definition),
        'submission values omit disabled fields'
    );
}

$renderer = new FieldRenderer();
$defaultHtml = $renderer->render($defaults['home']);
$same(true, str_contains($defaultHtml, 'name="name"'), 'renderer includes name');
$same(true, str_contains($defaultHtml, 'name="phone"'), 'renderer includes phone');
$same(true, str_contains($defaultHtml, 'name="message"'), 'renderer includes message');
$same(false, str_contains($defaultHtml, 'name="email"'), 'renderer omits disabled email');
$same(true, str_contains($defaultHtml, 'name="phone"') && preg_match('/name="phone"[^>]*required/', $defaultHtml) === 1, 'renderer marks required phone');
$customHtml = $renderer->render($definition);
$same(true, str_contains($customHtml, 'name="custom[delivery-date]"'), 'renderer includes custom text field');
$same(true, str_contains($customHtml, 'name="custom[delivery-date-2]"'), 'renderer includes custom select field');
$same(true, str_contains($customHtml, '<option value="Москва">Москва</option>'), 'renderer includes configured select option');
$same(true, preg_match('/name="custom\[delivery-date\]"[^>]*required/', $customHtml) === 1, 'renderer marks required custom field');
$same(true, strpos($customHtml, 'custom[delivery-date]') < strpos($customHtml, 'custom[delivery-date-2]'), 'renderer preserves custom field order');

$settingsPage = new SettingsPage($settings);
$settingsPage->register();
$settingsPage->settings();
$settingsPage->menu();
$same(true, isset($GLOBALS['theobroma_test_actions']['admin_menu']), 'settings page registers admin menu hook');
$same(true, isset($GLOBALS['theobroma_test_actions']['admin_enqueue_scripts']), 'settings page registers admin assets hook');
$same('array', $GLOBALS['theobroma_test_settings'][Settings::OPTION]['args']['type'] ?? null, 'settings option is registered as array');
$same('manage_options', $GLOBALS['theobroma_test_menus']['theobroma-contact-forms']['capability'] ?? null, 'settings page requires manage_options');
$settingsPage->assets('settings_page_theobroma-contact-forms');
$same(true, isset($GLOBALS['theobroma_test_scripts']['theobroma-contact-forms-admin']), 'settings page enqueues custom fields script');
$same(true, isset($GLOBALS['theobroma_test_styles']['theobroma-contact-forms-admin']), 'settings page enqueues custom fields styles');
$GLOBALS['theobroma_test_options'][Settings::OPTION] = $sanitized;
ob_start();
$settingsPage->render();
$settingsHtml = (string) ob_get_clean();
$same(true, str_contains($settingsHtml, 'data-add-custom-field'), 'settings page provides add field controls');
$same(true, str_contains($settingsHtml, '[custom_fields][0][label]'), 'settings page renders editable custom field rows');
$same(true, str_contains($settingsHtml, 'data-move-custom-field="up"'), 'settings page provides field ordering controls');
$same(true, str_contains($settingsHtml, 'class="theobroma-forms-admin"'), 'settings page uses the designed application shell');
$same(true, str_contains($settingsHtml, 'role="tablist"'), 'settings page exposes form navigation as an accessible tab list');
$same(true, str_contains($settingsHtml, 'data-form-tab="home"') && str_contains($settingsHtml, 'data-form-tab="cooperation"'), 'settings page provides a tab for each form');
$same(true, str_contains($settingsHtml, 'data-form-panel="cooperation" hidden'), 'only the active form panel is initially visible');
$same(true, substr_count($settingsHtml, 'class="theobroma-settings-card') >= 6, 'each form is organized into settings cards');
$same(true, str_contains($settingsHtml, 'data-custom-fields-empty'), 'custom fields builder has an empty state');
$same(true, str_contains($settingsHtml, 'class="theobroma-save-bar"'), 'settings page has a persistent save action bar');
$same(true, str_contains($settingsHtml, 'class="theobroma-switch__input"'), 'standard field controls use consistent switch markup');

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
    $same(true, function_exists('theobroma_contact_forms_values'), 'enabled values API is available');
}

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contact forms settings and submission model verified.\n";

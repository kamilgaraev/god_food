<?php

declare(strict_types=1);

use Theobroma\ContactForms\Settings;
use Theobroma\ContactForms\Submission;

$plugin = dirname(__DIR__);
$required = array($plugin . '/src/Settings.php', $plugin . '/src/Submission.php');
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

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contact forms settings and submission model verified.\n";

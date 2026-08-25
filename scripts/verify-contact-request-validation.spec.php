<?php

declare(strict_types=1);

function theobroma_contact_forms_validate(string $formId, array $values): bool {
    return $formId === 'cooperation'
        && ($values['email'] ?? '') === 'partner@example.test';
}
function theobroma_contact_forms_recipient(string $formId): string {
    return $formId === 'cooperation' ? 'sales@example.test' : 'owner@example.test';
}
function theobroma_contact_forms_notification_lines(string $formId, array $values): array {
    return array('Форма: ' . $formId, 'E-mail: ' . ($values['email'] ?? ''));
}
function theobroma_contact_forms_values(string $formId, array $values): array {
    return array('email' => $values['email'] ?? '');
}

$validator = dirname(__DIR__) . '/wp-content/themes/theobroma/inc/contact-request-validation.php';
if (!is_file($validator)) {
    fwrite(STDERR, "Contact request validator is missing.\n");
    exit(1);
}
require_once $validator;

$valid_corporate_request = array(
    'name' => 'Анна',
    'phone' => '+7 999 123-45-67',
    'consent' => '1',
    'honeypot' => '',
    'started_at' => 100,
    'request_type' => 'corporate_gift',
    'email' => '',
);

if (!theobroma_contact_request_is_valid($valid_corporate_request, 104)) {
    fwrite(STDERR, "Corporate requests must remain valid without the removed email field.\n");
    exit(1);
}

$spam_request = $valid_corporate_request;
$spam_request['honeypot'] = 'https://spam.example';
if (theobroma_contact_request_is_valid($spam_request, 104)) {
    fwrite(STDERR, "Honeypot submissions must still be rejected.\n");
    exit(1);
}

$notification_lines = theobroma_contact_request_lines(array(
    'name' => 'Анна',
    'phone' => '+7 999 123-45-67',
    'email' => '',
    'message' => 'Нужны подарки к декабрю',
    'company' => '',
    'gift_type' => '',
    'volume' => '',
    'branding' => '',
));
$expected_lines = array(
    'Имя: Анна',
    'Телефон: +7 999 123-45-67',
    'Комментарий: Нужны подарки к декабрю',
);
if ($notification_lines !== $expected_lines) {
    fwrite(STDERR, "Notifications must omit labels for fields removed from the form.\n");
    exit(1);
}

$standard_request = array(
    'name' => '',
    'phone' => '',
    'email' => 'partner@example.test',
    'message' => '',
    'consent' => '1',
    'honeypot' => '',
    'started_at' => 100,
);
foreach (array('theobroma_standard_contact_request_is_valid', 'theobroma_standard_contact_request_recipient', 'theobroma_standard_contact_request_lines', 'theobroma_standard_contact_request_values') as $function) {
    if (!function_exists($function)) {
        fwrite(STDERR, $function . " is missing.\n");
        exit(1);
    }
}
if (!theobroma_standard_contact_request_is_valid($standard_request, 'cooperation', 104)) {
    fwrite(STDERR, "Standard request must use configured plugin validation.\n");
    exit(1);
}
if (theobroma_standard_contact_request_recipient('cooperation', 'owner@example.test') !== 'sales@example.test') {
    fwrite(STDERR, "Configured form recipient must override the fallback.\n");
    exit(1);
}
if (theobroma_standard_contact_request_lines('cooperation', $standard_request) !== array('Форма: cooperation', 'E-mail: partner@example.test')) {
    fwrite(STDERR, "Standard notification lines must come from the plugin configuration.\n");
    exit(1);
}
if (theobroma_standard_contact_request_values('cooperation', $standard_request) !== array('email' => 'partner@example.test')) {
    fwrite(STDERR, "Disabled standard form fields must be omitted from stored values.\n");
    exit(1);
}

echo "Contact request validation accepts simplified forms and preserves anti-spam checks.\n";

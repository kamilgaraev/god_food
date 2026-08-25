<?php

declare(strict_types=1);

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

echo "Contact request validation accepts simplified forms and preserves anti-spam checks.\n";

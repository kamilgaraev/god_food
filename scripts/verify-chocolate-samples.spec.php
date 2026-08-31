<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$validator = $root . '/wp-content/themes/theobroma/inc/contact-request-validation.php';
$sampleService = $root . '/wp-content/themes/theobroma/inc/chocolate-sample-request.php';
$template = $root . '/wp-content/themes/theobroma/template-parts/pages/chocolate-samples.php';

require_once $validator;
require_once $sampleService;

$baseRequest = array(
    'company' => 'ООО Шоколадная карта',
    'inn' => '7701234567',
    'name' => 'Анна',
    'phone' => '+7 999 123-45-67',
    'email' => 'anna@example.test',
    'city' => 'Москва',
    'venue_type' => 'Кофейня',
    'message' => 'Хотим познакомиться с ассортиментом',
    'consent' => '1',
    'honeypot' => '',
    'started_at' => 100,
);

if (!function_exists('theobroma_chocolate_sample_request_is_valid')) {
    fwrite(STDERR, "Chocolate sample request validator is missing.\n");
    exit(1);
}

if (!theobroma_chocolate_sample_request_is_valid($baseRequest, 104)) {
    fwrite(STDERR, "A complete restaurant sample request must be accepted.\n");
    exit(1);
}

$individualRequest = $baseRequest;
$individualRequest['inn'] = '500100732259';
if (!theobroma_chocolate_sample_request_is_valid($individualRequest, 104)) {
    fwrite(STDERR, "A 12-digit individual entrepreneur INN must be accepted.\n");
    exit(1);
}

foreach (array(
    'missing company' => array('company' => ''),
    'missing INN' => array('inn' => ''),
    'invalid INN' => array('inn' => '123456789'),
    'non-numeric INN' => array('inn' => '7701abc234567'),
    'oversized company' => array('company' => str_repeat('А', 161)),
    'missing contact name' => array('name' => ''),
    'missing phone' => array('phone' => ''),
    'failed consent' => array('consent' => ''),
) as $label => $changes) {
    $request = array_merge($baseRequest, $changes);
    if (theobroma_chocolate_sample_request_is_valid($request, 104)) {
        fwrite(STDERR, "Sample request unexpectedly accepted: {$label}.\n");
        exit(1);
    }
}

$expectedLines = array(
    'Компания: ООО Шоколадная карта',
    'ИНН: 7701234567',
    'Тип заведения: Кофейня',
    'Город: Москва',
    'Имя: Анна',
    'Телефон: +7 999 123-45-67',
    'E-mail: anna@example.test',
    'Комментарий: Хотим познакомиться с ассортиментом',
);
if (!function_exists('theobroma_chocolate_sample_request_lines') || theobroma_chocolate_sample_request_lines($baseRequest) !== $expectedLines) {
    fwrite(STDERR, "Sample request notification must contain company details and contact data.\n");
    exit(1);
}

if (!is_file($template)) {
    fwrite(STDERR, "Chocolate samples page template is missing.\n");
    exit(1);
}

function wp_unslash(mixed $value): mixed { return $value; }
function sanitize_text_field(mixed $value): string { return trim((string) $value); }
function sanitize_textarea_field(mixed $value): string { return trim((string) $value); }
function sanitize_email(mixed $value): string { return trim((string) $value); }
function absint(mixed $value): int { return abs((int) $value); }
class WP_Error { public function __construct(mixed ...$args) {} }
function wp_insert_post(array $post, bool $wpError): int { $GLOBALS['sample_insert_count'] = ($GLOBALS['sample_insert_count'] ?? 0) + 1; $GLOBALS['sample_saved_post'] = $post; return 42; }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function update_post_meta(int $postId, string $key, mixed $value): void { $GLOBALS['sample_saved_meta'][$key] = $value; }
function wp_mail(string $to, string $subject, string $message): bool { $GLOBALS['sample_mail'] = compact('to', 'subject', 'message'); return $GLOBALS['sample_mail_result'] ?? true; }
function wp_salt(string $scheme): string { return 'test-salt-' . $scheme; }
function get_option(string $name, mixed $default = false): mixed { return $name === 'admin_email' ? 'owner@example.test' : ($GLOBALS['sample_options'][$name] ?? $default); }
function add_option(string $name, mixed $value, string $deprecated = '', bool $autoload = true): bool { if (array_key_exists($name, $GLOBALS['sample_options'] ?? array())) return false; $GLOBALS['sample_options'][$name] = $value; return true; }
function delete_option(string $name): bool { unset($GLOBALS['sample_options'][$name]); return true; }
function add_action(string $hook, callable|string $callback, int $priority = 10, int $acceptedArgs = 1): void {}
function wp_schedule_single_event(int $timestamp, string $hook, array $args = array()): bool { return true; }
function esc_url(string $value): string { return $value; }
function home_url(string $path = ''): string { return 'https://example.test' . $path; }
function get_template_directory_uri(): string { return 'https://example.test/theme'; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field(string $action, string $name): void { echo '<input type="hidden" name="' . $name . '" value="nonce">'; }
function theobroma_contact_antispam_fields(): void { echo '<input type="hidden" name="theobroma_form_started" value="100">'; }
function theobroma_page_url(string $title): string { return 'https://example.test/legal'; }

$savedId = theobroma_save_chocolate_sample_request($baseRequest, '192.0.2.10');
if ($savedId !== 42
    || ($GLOBALS['sample_saved_post']['post_content'] ?? '') !== implode("\n", $expectedLines)
    || ($GLOBALS['sample_saved_meta']['_theobroma_request_company'] ?? '') !== 'ООО Шоколадная карта'
    || ($GLOBALS['sample_saved_meta']['_theobroma_request_inn'] ?? '') !== '7701234567'
    || ($GLOBALS['sample_saved_meta']['_theobroma_request_mail_sent'] ?? '') !== '1'
    || ($GLOBALS['sample_mail']['subject'] ?? '') !== 'Запрос пробников шоколада — ООО Шоколадная карта'
    || ($GLOBALS['sample_mail']['message'] ?? '') !== implode("\n", $expectedLines)
) {
    fwrite(STDERR, "Sample request service must persist and email the complete application.\n");
    exit(1);
}

$duplicateResult = theobroma_save_chocolate_sample_request($baseRequest, '192.0.2.10');
if (!is_wp_error($duplicateResult) || ($GLOBALS['sample_insert_count'] ?? 0) !== 1) {
    fwrite(STDERR, "Repeated sample requests must be rate-limited before persistence.\n");
    exit(1);
}

for ($index = 1; $index <= 2; $index++) {
    $uniqueRequest = $baseRequest;
    $uniqueRequest['inn'] = '770123456' . $index;
    $uniqueRequest['phone'] = '+7 999 123-45-6' . $index;
    if (theobroma_save_chocolate_sample_request($uniqueRequest, '192.0.2.10') !== 42) {
        fwrite(STDERR, "The IP rate limit must allow three legitimate requests per window.\n");
        exit(1);
    }
}
$fourthRequest = $baseRequest;
$fourthRequest['inn'] = '7701234563';
$fourthRequest['phone'] = '+7 999 123-45-63';
if (!is_wp_error(theobroma_save_chocolate_sample_request($fourthRequest, '192.0.2.10'))) {
    fwrite(STDERR, "Changing INN and phone must not bypass the IP rate limit.\n");
    exit(1);
}

$GLOBALS['sample_mail_result'] = false;
$mailFailureRequest = $baseRequest;
$mailFailureRequest['inn'] = '500100732259';
$mailFailureId = theobroma_save_chocolate_sample_request($mailFailureRequest, '192.0.2.11');
if ($mailFailureId !== 42 || ($GLOBALS['sample_saved_meta']['_theobroma_request_mail_sent'] ?? '') !== '0') {
    fwrite(STDERR, "A saved request must record email delivery failure without losing the lead.\n");
    exit(1);
}

ob_start();
require $template;
$html = (string) ob_get_clean();

if (!preg_match('/name=["\']request_type["\'][^>]*value=["\']chocolate_samples["\']/u', $html)) {
    fwrite(STDERR, "Rendered samples form is missing its request type.\n");
    exit(1);
}

foreach (array(
    'company' => 'required',
    'inn' => 'required',
    'name' => 'required',
    'phone' => 'required',
) as $field => $attribute) {
    if (!preg_match('/name=["\']' . preg_quote($field, '/') . '["\'][^>]*\b' . preg_quote($attribute, '/') . '\b/u', $html)) {
        fwrite(STDERR, "Rendered samples form is missing required {$field} contract.\n");
        exit(1);
    }
}

if (!str_contains($html, 'Запросить') || !str_contains($html, 'пробники шоколада')) {
    fwrite(STDERR, "Rendered samples page is missing its primary heading.\n");
    exit(1);
}

echo "Chocolate sample requests require company details and render the dedicated form.\n";

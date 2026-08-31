<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);

function do_action(string $hook): void {}
function esc_html_e(string $text, string $domain = 'default'): void { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr_e(string $text, string $domain = 'default'): void { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wc_wp_theme_get_element_class_name(string $element): string { return ''; }
function wp_nonce_field(string $action, string $name): void
{
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="test-nonce">';
}

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$template = __DIR__ . '/../wp-content/themes/theobroma/woocommerce/myaccount/form-edit-account.php';
expect_true(is_file($template), 'The theme must provide an account profile form override.');

$user = (object) [
    'first_name' => 'Иван',
    'last_name' => 'Иванов',
    'display_name' => 'Existing "Name"',
    'user_email' => 'ivan@example.test',
];

ob_start();
require $template;
$html = (string) ob_get_clean();

expect_true(!str_contains($html, '<label for="account_display_name"'), 'The profile form must not show a display-name field.');
expect_true(!str_contains($html, 'account_display_name_description'), 'The profile form must not show the display-name review hint.');
expect_true(
    str_contains($html, '<input type="hidden" name="account_display_name" value="Existing &quot;Name&quot;">'),
    'The form must preserve the current display name when WooCommerce saves the profile.'
);

foreach (['account_first_name', 'account_last_name', 'account_email', 'password_current', 'password_1', 'password_2'] as $field) {
    expect_true(str_contains($html, 'name="' . $field . '"'), 'The profile form must keep the ' . $field . ' field.');
}

expect_true(str_contains($html, 'name="save-account-details-nonce"'), 'The profile form must keep WooCommerce nonce protection.');
expect_true(str_contains($html, 'name="action" value="save_account_details"'), 'The profile form must submit the WooCommerce save action.');

echo "Account profile form verification passed.\n";

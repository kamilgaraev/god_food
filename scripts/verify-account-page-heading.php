<?php

define('ABSPATH', __DIR__);

function get_header(): void {}
function get_footer(): void {}
function is_page($page): bool { return false; }
function have_posts(): bool
{
    static $has_post = true;
    if (!$has_post) {
        return false;
    }
    $has_post = false;
    return true;
}
function the_post(): void {}
function is_cart(): bool { return false; }
function is_checkout(): bool { return false; }
function is_account_page(): bool { return true; }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function the_content(): void { echo '<div class="woocommerce">CABINET</div>'; }
function wp_get_current_user(): object { return (object) ['display_name' => 'Test User', 'user_login' => 'test']; }
function esc_url($value): string { return (string) $value; }
function wc_get_account_endpoint_url($endpoint): string { return '/my-account/' . $endpoint . '/'; }
function do_action($hook): void {}

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ob_start();
require __DIR__ . '/../wp-content/themes/theobroma/page.php';
$page_html = ob_get_clean();

$heading = '<h1 class="account-page-title">ЛИЧНЫЙ КАБИНЕТ</h1>';
$heading_position = strpos($page_html, $heading);
$content_position = strpos($page_html, '<div class="woocommerce">');

expect_true($heading_position !== false, 'The account page must render a visible account-page-title heading.');
expect_true($content_position !== false && $heading_position < $content_position, 'The account title must appear before the WooCommerce layout.');
expect_true(!str_contains($page_html, 'screen-reader-text'), 'The account page title must not be visually hidden.');

ob_start();
require __DIR__ . '/../wp-content/themes/theobroma/woocommerce/myaccount/dashboard.php';
$dashboard_html = ob_get_clean();

expect_true(!str_contains($dashboard_html, 'account-eyebrow'), 'The dashboard panel must not duplicate the page title.');
expect_true(substr_count($page_html . $dashboard_html, 'ЛИЧНЫЙ КАБИНЕТ') === 1, 'The account title must be rendered exactly once.');

echo "Account page heading structure verification passed.\n";

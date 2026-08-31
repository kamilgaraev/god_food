<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);

final class RedirectCaptured extends RuntimeException
{
    public function __construct(public readonly string $url, public readonly int $status)
    {
        parent::__construct('Redirect captured');
    }
}

$registered_filters = array();
$registered_actions = array();
$formatted_address = '';

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $registered_filters;
    $registered_filters[$hook] = $callback;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $registered_actions;
    $registered_actions[$hook] = $callback;
}

function __(string $text, string $domain = 'default'): string { return $text; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_url(string $value): string { return $value; }
function wp_kses_post(string $value): string { return $value; }
function get_current_user_id(): int { return 7; }
function wc_get_account_formatted_address(string $type): string
{
    global $formatted_address;
    return $type === 'billing' ? $formatted_address : '';
}
function wc_get_endpoint_url(string $endpoint, string $value = '', string $permalink = ''): string
{
    return '/my-account/' . $endpoint . ($value !== '' ? '/' . $value : '') . '/';
}
function wc_get_page_permalink(string $page): string { return '/my-account/'; }
function wc_get_account_endpoint_url(string $endpoint): string { return '/my-account/' . $endpoint . '/'; }
function is_wc_endpoint_url(string $endpoint): bool { return $endpoint === 'edit-address'; }
function get_query_var(string $name): string { return $name === 'edit-address' ? 'shipping' : ''; }
function wp_safe_redirect(string $url, int $status = 302): never { throw new RedirectCaptured($url, $status); }
function wp_get_current_user(): object { return (object) ['display_name' => 'Иван', 'user_login' => 'ivan']; }
function do_action(string $hook, mixed ...$args): void {}

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$policy_file = __DIR__ . '/../wp-content/themes/theobroma/inc/account-addresses.php';
expect_true(is_file($policy_file), 'The theme must define its customer delivery-address policy.');
require $policy_file;

expect_true(isset($registered_filters['woocommerce_my_account_get_addresses']), 'The delivery-address policy must filter the account address list.');
expect_true(isset($registered_filters['woocommerce_my_account_edit_address_title']), 'The delivery-address policy must rename the WooCommerce edit-address form.');
expect_true(isset($registered_actions['template_redirect']), 'The delivery-address policy must handle legacy shipping links.');

$addresses = theobroma_account_delivery_addresses(array(
    'billing' => 'Платёжный адрес',
    'shipping' => 'Адрес доставки',
));
expect_true($addresses === array('billing' => 'Адрес доставки'), 'The account must expose one delivery address backed by checkout-compatible customer data.');
expect_true(theobroma_account_delivery_address_title('Платёжный адрес', 'billing') === 'Адрес доставки', 'The address edit form must not expose billing terminology.');

try {
    theobroma_redirect_legacy_shipping_address();
    throw new RuntimeException('The legacy shipping endpoint must redirect to the canonical delivery-address editor.');
} catch (RedirectCaptured $redirect) {
    expect_true($redirect->url === '/my-account/edit-address/billing/', 'The legacy shipping endpoint must redirect to the checkout-compatible address record.');
    expect_true($redirect->status === 302, 'The legacy shipping endpoint must use a temporary redirect.');
}

$template = __DIR__ . '/../wp-content/themes/theobroma/woocommerce/myaccount/my-address.php';
expect_true(is_file($template), 'The theme must provide a focused delivery-address template.');

ob_start();
require $template;
$empty_html = (string) ob_get_clean();

expect_true(substr_count($empty_html, '<article class="theobroma-address-card">') === 1, 'The account must render exactly one address card.');
expect_true(!str_contains(mb_strtolower($empty_html), 'платёж'), 'The delivery-address page must not mention a billing address.');
expect_true(str_contains($empty_html, 'Адрес доставки'), 'The page must clearly name the remaining address.');
expect_true(str_contains($empty_html, 'Добавить адрес доставки'), 'The empty state must offer a clear add action.');
expect_true(str_contains($empty_html, '/my-account/edit-address/billing/'), 'The action must edit the checkout-compatible customer address record.');

$formatted_address = 'Москва<br>ул. Тестовая, 7';
ob_start();
require $template;
$saved_html = (string) ob_get_clean();
expect_true(str_contains($saved_html, 'Москва<br>ул. Тестовая, 7'), 'The card must render the saved delivery address.');
expect_true(str_contains($saved_html, 'Изменить адрес'), 'A saved address must offer an edit action.');

ob_start();
require __DIR__ . '/../wp-content/themes/theobroma/woocommerce/myaccount/dashboard.php';
$dashboard_html = (string) ob_get_clean();
expect_true(!str_contains(mb_strtolower($dashboard_html), 'платёж'), 'The account dashboard must not mention billing data.');
expect_true(str_contains($dashboard_html, 'Адрес доставки'), 'The dashboard must link to the delivery address in singular form.');

echo "Account delivery-address behavior verification passed.\n";

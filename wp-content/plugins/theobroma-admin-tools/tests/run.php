<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wordpress/');

final class WP_Post
{
    public int $ID = 52;
}

$test_product_meta = [];
$test_can_edit_product = true;

function add_action(...$arguments): void {}
function add_filter(...$arguments): void {}
function do_action(...$arguments): void {}
function has_action(...$arguments): bool { return false; }
function apply_filters(string $hook, mixed $value, ...$arguments): mixed { return $value; }
function wp_nonce_field(string $action, string $name): void {}
function wp_verify_nonce(string $nonce, string $action): bool { return $nonce === 'valid' && $action === 'theobroma_save_fields'; }
function current_user_can(string $capability, int $postId): bool { return $GLOBALS['test_can_edit_product']; }
function wp_unslash(mixed $value): mixed { return $value; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function wp_kses_post(mixed $value): string { return (string) $value; }
function wp_strip_all_tags(string $value): string { return strip_tags($value); }
function sanitize_html_class(string $value): string { return preg_replace('/[^A-Za-z0-9_-]/', '', $value); }
function absint(mixed $value): int { return abs((int) $value); }
function get_post_meta(int $postId, string $key, bool $single): mixed
{
    if (array_key_exists($key, $GLOBALS['test_product_meta'][$postId] ?? [])) {
        return $GLOBALS['test_product_meta'][$postId][$key];
    }
    return match ($key) {
        '_theobroma_product_benefits', '_theobroma_marketplaces' => [],
        default => '',
    };
}
function update_post_meta(int $postId, string $key, mixed $value): void { $GLOBALS['test_product_meta'][$postId][$key] = $value; }
function checked(mixed $value, mixed $expected = true, bool $echo = true): string
{
    $markup = (string) $value === (string) $expected ? ' checked="checked"' : '';
    if ($echo) {
        echo $markup;
    }
    return $markup;
}
function esc_attr(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url(mixed $value): string { return (string) $value; }
function esc_url_raw(mixed $value): string { return (string) $value; }
function wc_implode_html_attributes(array $attributes): string
{
    $markup = [];
    foreach ($attributes as $name => $value) {
        $markup[] = esc_attr($name) . '="' . esc_attr($value) . '"';
    }
    return implode(' ', $markup);
}

final class WC_Product
{
    public function __construct(private int $id) {}
    public function get_id(): int { return $this->id; }
    public function get_meta(string $key, bool $single): mixed { return get_post_meta($this->id, $key, $single); }
    public function get_type(): string { return 'simple'; }
    public function get_sku(): string { return 'sku-' . $this->id; }
    public function get_name(): string { return 'Шоколад'; }
    public function get_permalink(): string { return 'https://example.test/product/' . $this->id; }
    public function get_image(...$arguments): string { return '<img alt="Шоколад">'; }
    public function get_short_description(): string { return 'Натуральный шоколад'; }
    public function get_price_html(): string { return '100 ₽'; }
    public function is_purchasable(): bool { return true; }
    public function is_in_stock(): bool { return true; }
    public function supports(string $feature): bool { return true; }
    public function add_to_cart_url(): string { return 'https://example.test/?add-to-cart=' . $this->id; }
    public function add_to_cart_description(): string { return 'Добавить шоколад в корзину'; }
}

require dirname(__DIR__) . '/theobroma-admin-tools.php';

ob_start();
try {
    Theobroma_Admin_Tools::render_product_box(new WP_Post());
    $markup = (string) ob_get_clean();
} catch (Throwable $exception) {
    ob_end_clean();
    fwrite(STDERR, sprintf("FAIL product meta box rendering: %s\n", $exception->getMessage()));
    exit(1);
}

foreach (range(0, 2) as $index) {
    $field = sprintf('name="theobroma_product_benefits[%d][title]"', $index);
    if (!str_contains($markup, $field)) {
        fwrite(STDERR, sprintf("FAIL product benefit title field %d was not rendered\n", $index + 1));
        exit(1);
    }
}

if (substr_count($markup, 'type="text"') < 3) {
    fwrite(STDERR, "FAIL product benefit title fields are not text inputs\n");
    exit(1);
}

echo "PASS product meta box renders all benefit title fields\n";

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function render_product_box(int $productId): string
{
    $post = new WP_Post();
    $post->ID = $productId;
    ob_start();
    Theobroma_Admin_Tools::render_product_box($post);
    return (string) ob_get_clean();
}

function render_product_card(int $productId, string $wrapper = 'article'): string
{
    $args = ['product' => new WC_Product($productId), 'wrapper_tag' => $wrapper, 'woocommerce_loop_hooks' => $wrapper === 'li'];
    ob_start();
    require dirname(__DIR__, 3) . '/themes/theobroma/template-parts/home/product-card.php';
    return (string) ob_get_clean();
}

expect(preg_match('/<input\b[^>]*type="checkbox"[^>]*name="theobroma_bestseller"[^>]*>/', $markup, $matches) === 1, 'Product editor must expose a bestseller checkbox.');
expect(!str_contains($matches[0], 'checked'), 'New products must not be marked as bestsellers by default.');

$_POST = ['theobroma_fields_nonce' => 'valid', 'theobroma_bestseller' => '1'];
Theobroma_Admin_Tools::save_product_fields(52);
expect(get_post_meta(52, '_theobroma_bestseller', true) === '1', 'Saving a checked bestseller checkbox must enable the badge.');
expect(preg_match('/<input\b[^>]*name="theobroma_bestseller"[^>]*checked="checked"/', render_product_box(52)) === 1, 'The checkbox must remain checked after saving and reopening.');

$badge = '<span class="home-product-card__badge">Бестселлер</span>';
foreach (['article', 'li'] as $wrapper) {
    expect(!str_contains(render_product_card(53, $wrapper), $badge), 'An unmarked product must have no badge, including in the first position.');
    expect(substr_count(render_product_card(52, $wrapper), $badge) === 1, 'A marked product must show the existing badge in homepage and catalog cards.');
}

$_POST = ['theobroma_fields_nonce' => 'valid', 'theobroma_bestseller' => '1'];
Theobroma_Admin_Tools::save_product_fields(53);
expect(str_contains(render_product_card(52), $badge) && str_contains(render_product_card(53), $badge), 'Multiple products can be marked independently.');

$_POST = ['theobroma_fields_nonce' => 'valid'];
Theobroma_Admin_Tools::save_product_fields(52);
expect(get_post_meta(52, '_theobroma_bestseller', true) === '0', 'Saving an unchecked checkbox must remove the bestseller flag.');
expect(!str_contains(render_product_card(52), $badge), 'Removing the flag must remove the badge.');
expect(str_contains(render_product_card(53), $badge), 'Changing one product must preserve another product\'s bestseller flag.');
expect(preg_match('/<input\b[^>]*name="theobroma_bestseller"[^>]*checked="checked"/', render_product_box(52)) === 0, 'The removed flag must remain unchecked after reopening.');

foreach ([[], ['theobroma_fields_nonce' => 'invalid']] as $request) {
    $_POST = $request;
    Theobroma_Admin_Tools::save_product_fields(53);
    expect(get_post_meta(53, '_theobroma_bestseller', true) === '1', 'Missing or invalid nonce must preserve the existing bestseller flag, including quick edits.');
}
$test_can_edit_product = false;
$_POST = ['theobroma_fields_nonce' => 'valid'];
Theobroma_Admin_Tools::save_product_fields(53);
expect(get_post_meta(53, '_theobroma_bestseller', true) === '1', 'Users without product edit permission must not change the flag.');
$test_can_edit_product = true;

define('DOING_AUTOSAVE', true);
Theobroma_Admin_Tools::save_product_fields(53);
expect(get_post_meta(53, '_theobroma_bestseller', true) === '1', 'Autosave must preserve the existing bestseller flag.');

echo "PASS bestseller checkbox saves, reopens, clears and controls shared product badges with nonce, permission and autosave guards\n";

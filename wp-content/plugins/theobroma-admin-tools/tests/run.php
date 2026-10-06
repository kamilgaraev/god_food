<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wordpress/');

final class WP_Post
{
    public int $ID = 52;
}

function add_action(...$arguments): void {}
function add_filter(...$arguments): void {}
function wp_nonce_field(string $action, string $name): void {}
function absint(mixed $value): int { return abs((int) $value); }
function get_post_meta(int $postId, string $key, bool $single): mixed
{
    return match ($key) {
        '_theobroma_product_benefits', '_theobroma_marketplaces' => [],
        default => '',
    };
}
function esc_attr(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url(mixed $value): string { return (string) $value; }
function get_current_screen(): ?object { return $GLOBALS['test_screen'] ?? null; }
function plugin_dir_url(string $file): string { return 'https://example.test/admin-tools/'; }
function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function wp_enqueue_style(string $handle, string $url, array $dependencies, string $version): void
{
    $GLOBALS['test_styles'][$handle] = compact('url', 'dependencies', 'version');
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

$columns = Theobroma_Admin_Tools::add_product_columns(['name' => 'Имя', 'sku' => 'Артикул']);
if (isset($columns['theobroma_sku']) || !isset($columns['sku'], $columns['theobroma_source'])) {
    fwrite(STDERR, "FAIL product list must keep the sortable WooCommerce SKU without a duplicate\n");
    exit(1);
}
$columns = Theobroma_Admin_Tools::add_product_columns(['name' => 'Имя']);
if (!isset($columns['theobroma_sku'], $columns['theobroma_source'])) {
    fwrite(STDERR, "FAIL product list must retain a SKU fallback when WooCommerce does not provide one\n");
    exit(1);
}
echo "PASS product list avoids duplicate SKU and retains its fallback\n";

foreach ([['edit.php', null], ['edit.php', 'post'], ['post.php', 'product'], ['post-new.php', 'product'], ['admin.php', 'product']] as [$hook, $postType]) {
    $GLOBALS['test_styles'] = [];
    $GLOBALS['test_screen'] = $postType === null ? null : (object) ['post_type' => $postType];
    Theobroma_Admin_Tools::enqueue_product_list_assets($hook);
    if ($GLOBALS['test_styles'] !== []) {
        fwrite(STDERR, "FAIL product list CSS must not load on unrelated admin screens\n");
        exit(1);
    }
}
$GLOBALS['test_screen'] = (object) ['post_type' => 'product'];
Theobroma_Admin_Tools::enqueue_product_list_assets('edit.php');
$style = $GLOBALS['test_styles']['theobroma-product-list'] ?? null;
if (!$style || !str_ends_with($style['url'], 'assets/product-list.css') || !ctype_digit($style['version'])) {
    fwrite(STDERR, "FAIL product list must enqueue its versioned stylesheet\n");
    exit(1);
}
echo "PASS product list stylesheet loads only on the product list screen\n";

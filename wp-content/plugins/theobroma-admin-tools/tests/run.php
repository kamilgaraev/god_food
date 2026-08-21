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

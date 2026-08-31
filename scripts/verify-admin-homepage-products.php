<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WC_Product {
    public function __construct(private int $id, private string $name, private string $sku) {}
    public function get_id(): int { return $this->id; }
    public function get_name(): string { return $this->name; }
    public function get_sku(): string { return $this->sku; }
}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function current_user_can(string $capability): bool { return $capability === 'manage_options'; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_textarea(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function absint(mixed $value): int { return abs((int) $value); }
function wp_nonce_field(string $action): void { echo '<input type="hidden" value="' . esc_attr($action) . '">'; }
function submit_button(string $label): void { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function theobroma_content(string $key): string {
    return array(
        'homepage_product_1' => '13',
        'homepage_product_2' => '11',
        'homepage_product_3' => '14',
        'homepage_product_4' => '12',
        'cacao_enabled' => '1',
        'cacao_default_percentage' => '80',
        'cacao_59_enabled' => '1',
        'cacao_65_enabled' => '1',
        'cacao_68_enabled' => '1',
        'cacao_70_enabled' => '1',
        'cacao_80_enabled' => '1',
    )[$key] ?? '';
}
function wc_get_products(array $args): array {
    return array(
        new WC_Product(11, 'Шоколад 70%', 'sku-70'),
        new WC_Product(12, 'Шоколад 80%', 'sku-80'),
        new WC_Product(13, 'Какао', 'sku-cacao'),
        new WC_Product(14, 'Шоколад с малиной', 'sku-raspberry'),
    );
}
function theobroma_homepage_products(): array {
    $products = wc_get_products(array());
    $by_id = array_column(array_map(static fn(WC_Product $product): array => array($product->get_id(), $product), $products), 1, 0);
    return array($by_id[13], $by_id[11], $by_id[14], $by_id[12]);
}
function wc_get_product(int $id): WC_Product|false {
    if ($id === 15) {
        return new WC_Product(15, 'Скрытый товар', 'sku-hidden');
    }
    foreach (wc_get_products(array()) as $product) {
        if ($product->get_id() === $id) {
            return $product;
        }
    }
    return false;
}
function theobroma_product_is_home_eligible(WC_Product $product): bool {
    return $product->get_id() !== 15;
}

require_once dirname(__DIR__) . '/wp-content/plugins/theobroma-admin-tools/theobroma-admin-tools.php';

ob_start();
Theobroma_Admin_Tools::render_content_settings();
$html = (string) ob_get_clean();

if (substr_count($html, 'name="settings[homepage_product_') !== 4) {
    throw new RuntimeException('Homepage settings must render exactly four ordered product selectors.');
}
foreach (array(13, 11, 14, 12) as $slot => $product_id) {
    $field = 'name="settings[homepage_product_' . ($slot + 1) . ']"';
    $pattern = '~<select[^>]*' . preg_quote($field, '~') . '[^>]*>(.*?)</select>~s';
    if (preg_match($pattern, $html, $matches) !== 1 || !str_contains($matches[1], 'value="' . $product_id . '" selected')) {
        throw new RuntimeException('Homepage product selector ' . ($slot + 1) . ' must preserve its configured product.');
    }
}
if (!str_contains($html, 'Шоколад с малиной') || !str_contains($html, 'sku-raspberry')) {
    throw new RuntimeException('Homepage product selectors must identify products by name and SKU.');
}
if (!str_contains($html, 'Ваш процент какао')
    || !str_contains($html, 'name="settings[cacao_heading]"')
    || !str_contains($html, 'name="settings[cacao_intro]"')
    || !str_contains($html, 'name="settings[cacao_button_label]"')
    || substr_count($html, 'name="settings[cacao_59_enabled]"') !== 2
    || !str_contains($html, 'name="settings[cacao_59_label]"')
    || !str_contains($html, 'name="settings[cacao_59_description]"')) {
    throw new RuntimeException('Cacao block settings must expose section copy and profile controls.');
}
$defaultPattern = '~<select[^>]*name="settings\[cacao_default_percentage\]"[^>]*>(.*?)</select>~s';
if (preg_match($defaultPattern, $html, $defaultMatches) !== 1 || !str_contains($defaultMatches[1], 'value="80" selected')) {
    throw new RuntimeException('Cacao block settings must preserve the configured default percentage.');
}
$normalizedCacao = Theobroma_Admin_Tools::normalize_cacao_settings(array(
    'cacao_enabled' => 'yes',
    'cacao_default_percentage' => '99',
    'cacao_59_enabled' => '1',
    'cacao_65_enabled' => 'unexpected',
));
if (($normalizedCacao['cacao_enabled'] ?? null) !== '0'
    || ($normalizedCacao['cacao_59_enabled'] ?? null) !== '1'
    || ($normalizedCacao['cacao_65_enabled'] ?? null) !== '0'
    || ($normalizedCacao['cacao_default_percentage'] ?? null) !== '59') {
    throw new RuntimeException('Cacao settings must normalize flags and choose an enabled default percentage.');
}

if (!method_exists(Theobroma_Admin_Tools::class, 'normalize_homepage_product_settings')) {
    throw new RuntimeException('Homepage product settings must expose a normalization contract.');
}
$normalized = Theobroma_Admin_Tools::normalize_homepage_product_settings(array(
    'homepage_product_1' => '13',
    'homepage_product_2' => '13',
    'homepage_product_3' => '15',
    'homepage_product_4' => '12',
    'footer_address' => 'Factory',
));
if ($normalized !== array(
    'homepage_product_1' => '13',
    'homepage_product_2' => '',
    'homepage_product_3' => '',
    'homepage_product_4' => '12',
    'footer_address' => 'Factory',
)) {
    throw new RuntimeException('Homepage product settings must reject duplicates, unavailable products, and unknown IDs.');
}

echo "Homepage admin product selectors verified\n";

<?php

declare(strict_types=1);

function esc_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url(mixed $value): string { return (string) $value; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function home_url(string $path = ''): string { return 'https://example.test' . $path; }
function get_template_directory_uri(): string { return 'https://example.test/theme'; }
function theobroma_content(string $key): string { return $key; }
function theobroma_page_url(string $title): string { return 'https://example.test/legal'; }
function wc_get_products(array $args): array { return array(); }
function wp_nonce_field(string $action, string $name): void {
    echo '<input type="hidden" name="' . esc_html($name) . '" value="nonce">';
}
function theobroma_contact_antispam_fields(): void {
    echo '<input type="hidden" name="theobroma_form_started" value="1">';
    echo '<input type="text" name="theobroma_website" value="">';
}

function render_template(string $path): string {
    ob_start();
    require $path;
    return (string) ob_get_clean();
}

/** @return list<string> */
function lead_field_names(string $html): array {
    preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"[^>]*>/i', $html, $matches);
    $service_fields = array(
        'action',
        'request_type',
        'theobroma_contact_nonce',
        'theobroma_form_started',
        'theobroma_website',
        'consent',
    );

    return array_values(array_filter(
        array_unique($matches[1]),
        static fn(string $name): bool => !in_array($name, $service_fields, true)
    ));
}

$theme = dirname(__DIR__) . '/wp-content/themes/theobroma/template-parts';
$cases = array(
    'main contact form' => array($theme . '/contact-section.php', array('name', 'phone')),
    'cooperation form' => array($theme . '/pages/cooperation.php', array('name', 'phone')),
    'corporate gifts form' => array($theme . '/pages/corporate-gifts.php', array('name', 'phone', 'message')),
);

$failures = array();
foreach ($cases as $label => [$path, $expected]) {
    $actual = lead_field_names(render_template($path));
    if ($actual !== $expected) {
        $failures[] = sprintf('%s: expected [%s], got [%s]', $label, implode(', ', $expected), implode(', ', $actual));
    }
}

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Lead forms expose only the minimum contact fields.\n";

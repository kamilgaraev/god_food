<?php
declare(strict_types=1);

// Standalone contract tests. Optional fixture output is always outside the checkout.
$option = array();
$can_manage = true;
$registrations = array();
function add_action(...$args): void {}
function current_user_can(string $capability): bool { return $GLOBALS['can_manage'] && $capability === 'manage_options'; }
function wp_die(string $message): never { throw new RuntimeException($message); }
function get_option(string $name, mixed $default = false): mixed { return $GLOBALS['option']; }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_textarea_field(string $value): string { return trim(strip_tags($value)); }
function wp_attachment_is_image(int $id): bool { return in_array($id, array(10, 20), true); }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $value): string { return esc_html($value); }
function esc_textarea(string $value): string { return esc_html($value); }
function esc_url(string $value): string { return esc_html($value); }
function get_template_directory_uri(): string { return '/wp-content/themes/theobroma'; }
function admin_url(string $path): string { return '/wp-admin/' . $path; }
function checked(bool $value): void { if ($value) echo 'checked'; }
function settings_errors(): void {}
function settings_fields(string $group): void {
    echo '<input name="option_page" value="' . esc_attr($group) . '"><input name="_wpnonce" value="fixture-nonce">';
}
function submit_button(string $label): void { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function register_setting(string $group, string $name, array $args): void { $GLOBALS['registrations'] = array($group, $name, $args); }
function wp_get_attachment_image(int $id, string $size, bool $icon, array $attrs): string {
    $GLOBALS['image_call'] = array($id, $size, $attrs);
    return '<img src="/media/' . $id . '.jpg" alt="' . esc_attr($attrs['alt']) . '" srcset="/media/' . $id . '.jpg 1200w" sizes="' . esc_attr($attrs['sizes']) . '">';
}
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function render_section(string $name): string {
    ob_start();
    include dirname(__DIR__) . '/wp-content/themes/theobroma/template-parts/corporate/' . $name . '.php';
    return (string) ob_get_clean();
}
require dirname(__DIR__) . '/wp-content/themes/theobroma/inc/corporate-sections.php';
require dirname(__DIR__) . '/wp-content/themes/theobroma/inc/admin-corporate-sections.php';

$defaults = theobroma_corporate_sections_defaults();
check(theobroma_corporate_sections() == $defaults, 'Unsaved settings must have useful initial content.');
$malformed = theobroma_sanitize_corporate_sections(array('portfolio' => array(
    'enabled' => '0', 'title' => array('invalid'), 'intro' => '<b>Вступление</b>',
    'items' => array(
        'bad', array('title' => ''),
        array('title' => '<b>Второй</b>', 'text' => "<i>Строка</i>\nЕщё строка", 'attachment_id' => 20, 'asset' => '../../secrets', 'alt' => '" onerror="alert(1)'),
        array('title' => 'Первый', 'attachment_id' => 10),
        array('title' => 'Не фото', 'attachment_id' => 99),
        array('title' => 'Отрицательный ID', 'attachment_id' => -10),
    ),
)));
check(!$malformed['portfolio']['enabled'], 'Explicit visibility off must be kept.');
check($malformed['portfolio']['title'] === $defaults['portfolio']['title'], 'Malformed heading gets a readable fallback.');
check(array_column($malformed['portfolio']['items'], 'title') === array('Второй', 'Первый', 'Не фото', 'Отрицательный ID'), 'Valid rows preserve submitted order.');
check(array_column($malformed['portfolio']['items'], 'attachment_id') === array(20, 10, 0, 0), 'Only image attachments with positive IDs are accepted.');
check($malformed['portfolio']['items'][0]['asset'] === '', 'Fallback assets must stay on the allowlist.');
check($malformed['portfolio']['items'][0]['text'] === "Строка\nЕщё строка", 'Descriptions support plain text and line breaks.');
check(theobroma_sanitize_corporate_sections($malformed) === $malformed, 'Sanitization is idempotent (first Settings API save).');
$many = array_fill(0, 20, array('title' => 'Этап', 'text' => array('invalid')));
check(count(theobroma_sanitize_corporate_sections(array('process' => array('items' => $many)))['process']['items']) === 12, 'Lists are bounded.');
foreach (array('portfolio', 'process') as $section) {
    $option = array($section => array('items' => ''));
    check(render_section($section) === '', 'Deleting every row hides ' . $section . ' without resurrecting defaults.');
    $option = array($section => array('enabled' => false));
    check(render_section($section) === '', 'Visibility switch hides ' . $section . '.');
}
$option = array('portfolio' => $malformed['portfolio']);
$option['portfolio']['enabled'] = true;
$html = render_section('portfolio');
check(substr_count($html, 'class="cg-portfolio-card"') === 2, 'Missing photos are not rendered.');
check(strpos($html, 'Второй') < strpos($html, 'Первый'), 'Storefront keeps saved order.');
check(!str_contains($html, 'alt="" onerror="'), 'Photo attributes are escaped.');
check($GLOBALS['image_call'][1] === 'large' && str_contains($html, 'srcset='), 'Uploaded photos use WordPress responsive large images.');
theobroma_register_corporate_sections_setting();
check($registrations[0] === $registrations[1] && $registrations[2]['sanitize_callback'] === 'theobroma_sanitize_corporate_sections', 'Settings API group and sanitizer match.');
$option = array();
ob_start();
theobroma_render_corporate_sections_admin();
$admin = (string) ob_get_clean();
check(str_contains($admin, 'action="/wp-admin/options.php"') && str_contains($admin, 'name="_wpnonce"'), 'Editor uses the protected WordPress Settings API save.');
check(str_contains($admin, '[portfolio][items]" value=""') && str_contains($admin, '[process][items]" value=""'), 'Both lists can be saved empty.');
$can_manage = false;
try {
    theobroma_render_corporate_sections_admin();
    throw new LogicException('Editor allowed an unauthorized user.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'Недостаточно прав'), 'Editor rejects users without manage_options.');
}
$can_manage = true;

if (isset($argv[1])) {
    $directory = realpath($argv[1]);
    $checkout = realpath(dirname(__DIR__));
    check($directory !== false && strtolower($directory) !== strtolower($checkout) && !str_starts_with(strtolower($directory), strtolower($checkout) . DIRECTORY_SEPARATOR), 'Fixture directory must exist outside checkout.');
    $head = '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    $styles = '';
    foreach (array('style.css', 'assets/css/home-redesign.css', 'assets/css/corporate-gifts.css', 'assets/css/design-system.css') as $file) {
        $styles .= '<link rel="stylesheet" href="' . get_template_directory_uri() . '/' . $file . '">';
    }
    $html = $head . $styles . '<body><main class="corporate-redesign" style="padding-top:3rem">' . render_section('portfolio') . render_section('process') . '<section id="corporate-request" tabindex="-1">Заявка</section></main><script src="' . get_template_directory_uri() . '/assets/js/corporate-gifts.js"></script></body></html>';
    file_put_contents($directory . '/storefront.html', $html);
    file_put_contents($directory . '/admin.html', $head . '<link rel="stylesheet" href="' . get_template_directory_uri() . '/assets/css/admin-corporate-sections.css"><body>' . $admin . '<script src="' . get_template_directory_uri() . '/assets/js/admin-corporate-sections.js"></script></body></html>');
}
echo "Corporate sections: sanitization, ordering, visibility, permissions and rendering passed.\n";

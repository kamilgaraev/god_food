<?php

declare(strict_types=1);

use Theobroma\PhotoShowcases\DefaultImages;
use Theobroma\PhotoShowcases\Plugin;
use Theobroma\PhotoShowcases\Renderer;
use Theobroma\PhotoShowcases\Settings;

$GLOBALS['theobroma_photo_test_products'] = array();
$GLOBALS['theobroma_photo_test_image_ids'] = array();
$GLOBALS['theobroma_photo_test_attachment_alts'] = array();
$GLOBALS['theobroma_photo_test_options'] = array();
$GLOBALS['theobroma_photo_test_actions'] = array();
$GLOBALS['theobroma_photo_test_styles'] = array();
$GLOBALS['theobroma_photo_test_front_page'] = false;
$GLOBALS['theobroma_photo_test_page'] = '';

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['theobroma_photo_test_actions'][$hook][] = $callback;
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_textarea_field(string $value): string
{
    return trim(strip_tags($value));
}

/** @return array<int, object> */
function wc_get_products(array $args): array
{
    return $GLOBALS['theobroma_photo_test_products'];
}

function wp_attachment_is_image(int $attachmentId): bool
{
    return in_array($attachmentId, $GLOBALS['theobroma_photo_test_image_ids'], true);
}

function get_option(string $name, mixed $default = false): mixed
{
    return array_key_exists($name, $GLOBALS['theobroma_photo_test_options'])
        ? $GLOBALS['theobroma_photo_test_options'][$name]
        : $default;
}

function get_post_meta(int $postId, string $key, bool $single = false): mixed
{
    return $key === '_wp_attachment_image_alt'
        ? ($GLOBALS['theobroma_photo_test_attachment_alts'][$postId] ?? '')
        : '';
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(string $value): string
{
    return esc_html($value);
}

function wp_get_attachment_image(int $attachmentId, string|array $size = 'thumbnail', bool $icon = false, string|array $attr = ''): string
{
    if (!wp_attachment_is_image($attachmentId)) {
        return '';
    }

    $attributes = is_array($attr) ? $attr : array();

    return sprintf(
        '<img data-id="%d" class="%s" alt="%s" loading="%s" decoding="%s">',
        $attachmentId,
        esc_attr((string) ($attributes['class'] ?? '')),
        esc_attr((string) ($attributes['alt'] ?? '')),
        esc_attr((string) ($attributes['loading'] ?? '')),
        esc_attr((string) ($attributes['decoding'] ?? ''))
    );
}

function plugins_url(string $path, string $plugin): string
{
    return 'https://example.test/plugins/theobroma-photo-showcases/' . ltrim($path, '/');
}

function wp_enqueue_style(string $handle, string $src, array $dependencies = array(), string|bool|null $version = false): void
{
    $GLOBALS['theobroma_photo_test_styles'][$handle] = compact('src', 'dependencies', 'version');
}

function is_front_page(): bool
{
    return $GLOBALS['theobroma_photo_test_front_page'];
}

function is_page(string $title): bool
{
    return $GLOBALS['theobroma_photo_test_page'] === $title;
}

final class PhotoShowcaseProductStub
{
    public function __construct(private readonly int $imageId)
    {
    }

    public function get_image_id(): int
    {
        return $this->imageId;
    }
}

$plugin = dirname(__DIR__);
$required = array(
    $plugin . '/src/Settings.php',
    $plugin . '/src/DefaultImages.php',
    $plugin . '/src/Renderer.php',
    $plugin . '/src/Plugin.php',
);

foreach ($required as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, basename($file) . " is missing.\n");
        exit(1);
    }

    require_once $file;
}

$failures = array();
$same = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
};

$settings = new Settings();
$defaults = $settings->defaults();
$same(array('home', 'corporate'), array_keys($defaults), 'defaults expose only supported locations');
$same(true, $defaults['home']['enabled'] ?? null, 'home is published by default');
$same('Вкус в деталях', $defaults['home']['eyebrow'] ?? null, 'home has editorial default copy');
$same('Подарки, которые запоминают', $defaults['corporate']['title'] ?? null, 'corporate has business default copy');
$same(array(), $defaults['corporate']['images'] ?? null, 'default collection starts without persisted images');

$rows = array();
for ($id = 1; $id <= 10; $id++) {
    $rows[] = array(
        'attachment_id' => (string) $id,
        'alt' => ' Alt ' . $id . ' <script>bad</script> ',
        'caption' => " Подпись {$id} <b>bold</b> ",
    );
}
$rows[] = array('attachment_id' => '2', 'alt' => 'Дубликат', 'caption' => 'Дубликат');
$rows[] = array('attachment_id' => '-7', 'alt' => 'Invalid', 'caption' => 'Invalid');

$sanitized = $settings->sanitize(array(
    'home' => array(
        'enabled' => '0',
        'eyebrow' => '  Детали <strong>вкуса</strong> ',
        'title' => '  Настоящий шоколад  ',
        'description' => " Первый ряд <em>описания</em> ",
        'images' => $rows,
    ),
    'corporate' => array(
        'enabled' => '1',
        'images' => array(array('attachment_id' => '42', 'alt' => '', 'caption' => 'Кейс')),
    ),
    'forged' => array('enabled' => '1', 'title' => 'Не сохранять'),
));

$same(array('home', 'corporate'), array_keys($sanitized), 'unknown location is dropped');
$same(false, $sanitized['home']['enabled'] ?? null, 'disabled collection remains disabled');
$same('Детали вкуса', $sanitized['home']['eyebrow'] ?? null, 'eyebrow is sanitized');
$same('Первый ряд описания', $sanitized['home']['description'] ?? null, 'description is sanitized');
$same(8, count($sanitized['home']['images'] ?? array()), 'image collection is capped at eight');
$same(array(1, 2, 3, 4, 5, 6, 7, 8), array_column($sanitized['home']['images'], 'attachment_id'), 'image ids stay positive unique and ordered');
$same('Alt 1 bad', $sanitized['home']['images'][0]['alt'] ?? null, 'image alt is sanitized');
$same('Подпись 1 bold', $sanitized['home']['images'][0]['caption'] ?? null, 'image caption is sanitized');
$same('Подарки, которые запоминают', $sanitized['corporate']['title'] ?? null, 'missing copy falls back to location defaults');
$same(42, $sanitized['corporate']['images'][0]['attachment_id'] ?? null, 'valid corporate attachment is preserved');

$GLOBALS['theobroma_photo_test_products'] = array(
    new PhotoShowcaseProductStub(21),
    new PhotoShowcaseProductStub(0),
    new PhotoShowcaseProductStub(22),
    new PhotoShowcaseProductStub(21),
    new PhotoShowcaseProductStub(23),
);
$GLOBALS['theobroma_photo_test_image_ids'] = array(21, 23);
$same(array(21, 23), (new DefaultImages())->ids(5), 'fallback keeps only unique valid product images');
$same(array(21), (new DefaultImages())->ids(1), 'fallback respects requested limit');

$GLOBALS['theobroma_photo_test_image_ids'] = array(31, 32, 33);
$GLOBALS['theobroma_photo_test_attachment_alts'] = array(31 => 'Шоколад 70%', 32 => 'Набор в коробке');
$rendererSettings = $settings->defaults();
$rendererSettings['home']['images'] = array(
    array('attachment_id' => 31, 'alt' => '', 'caption' => 'Ручная работа'),
    array('attachment_id' => 32, 'alt' => 'Своя подпись', 'caption' => ''),
);
$rendererSettings['corporate']['images'] = array(
    array('attachment_id' => 33, 'alt' => '', 'caption' => 'Для команды'),
);
$renderer = new Renderer();
$homeHtml = $renderer->html('home', $rendererSettings);
$same(true, str_contains($homeHtml, 'theobroma-photo-showcase--home'), 'home renderer uses editorial modifier');
$same(true, str_contains($homeHtml, 'aria-labelledby="theobroma-photo-showcase-home-title"'), 'home renderer links its accessible title');
$same(true, str_contains($homeHtml, 'data-id="31"') && str_contains($homeHtml, 'alt="Шоколад 70%"'), 'renderer falls back to attachment alt');
$same(true, str_contains($homeHtml, 'data-id="32"') && str_contains($homeHtml, 'alt="Своя подпись"'), 'configured alt overrides attachment alt');
$same(true, str_contains($homeHtml, '<figcaption>Ручная работа</figcaption>'), 'renderer includes configured caption');
$same(true, str_contains($homeHtml, 'loading="lazy"') && str_contains($homeHtml, 'decoding="async"'), 'renderer requests deferred responsive images');
$corporateHtml = $renderer->html('corporate', $rendererSettings);
$same(true, str_contains($corporateHtml, 'theobroma-photo-showcase--corporate'), 'corporate renderer uses business modifier');
$same(true, str_contains($corporateHtml, '<span aria-hidden="true">01</span>'), 'corporate renderer numbers the photo series');
$rendererSettings['corporate']['enabled'] = false;
$same('', $renderer->html('corporate', $rendererSettings), 'disabled collection renders no empty section');
$same('', $renderer->html('unknown', $rendererSettings), 'unknown location renders nothing');

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin . '/');
}
require_once $plugin . '/theobroma-photo-showcases.php';
$same(true, function_exists('theobroma_photo_showcase_html'), 'plugin exposes guarded theme API');
$same(true, isset($GLOBALS['theobroma_photo_test_actions']['wp_enqueue_scripts']), 'plugin registers frontend assets hook');

$GLOBALS['theobroma_photo_test_products'] = array(new PhotoShowcaseProductStub(31), new PhotoShowcaseProductStub(32));
$GLOBALS['theobroma_photo_test_image_ids'] = array(31, 32);
$GLOBALS['theobroma_photo_test_options'] = array();
$fallbackHtml = theobroma_photo_showcase_html('home');
$same(true, str_contains($fallbackHtml, 'data-id="31"'), 'first run uses valid WooCommerce product images');
$GLOBALS['theobroma_photo_test_options'][Settings::OPTION] = array(
    'home' => array('enabled' => '0', 'images' => array()),
    'corporate' => array('enabled' => '1', 'images' => array()),
);
$same('', theobroma_photo_showcase_html('home'), 'saved disabled collection does not restore fallback images');

$GLOBALS['theobroma_photo_test_front_page'] = true;
Plugin::instance()->enqueueFrontendAssets();
$same(true, isset($GLOBALS['theobroma_photo_test_styles']['theobroma-photo-showcases']), 'frontend stylesheet loads on home');

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Photo showcase settings and default images verified.\n";

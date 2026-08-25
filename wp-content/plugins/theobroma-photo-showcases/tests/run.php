<?php

declare(strict_types=1);

use Theobroma\PhotoShowcases\DefaultImages;
use Theobroma\PhotoShowcases\Settings;

$GLOBALS['theobroma_photo_test_products'] = array();
$GLOBALS['theobroma_photo_test_image_ids'] = array();

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

if ($failures !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Photo showcase settings and default images verified.\n";

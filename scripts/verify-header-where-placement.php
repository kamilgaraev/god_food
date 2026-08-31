<?php
declare(strict_types=1);

function bloginfo(string $key): void { echo $key === 'charset' ? 'UTF-8' : ''; }
function wp_head(): void {}
function body_class(): void {}
function wp_body_open(): void {}
function esc_url(string $value): string { return $value; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function home_url(string $path = '/'): string { return 'https://example.test' . $path; }
function wp_login_url(): string { return 'https://example.test/login'; }
function theobroma_page_url(string $title): string { return 'https://example.test/' . rawurlencode($title); }
function theobroma_content(string $key): string { return $key; }
function get_stylesheet_directory_uri(): string { return 'https://example.test/theme'; }
function is_user_logged_in(): bool { return false; }
function is_front_page(): bool { return true; }

ob_start();
require dirname(__DIR__) . '/wp-content/themes/theobroma/header.php';
$html = (string) ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($document);

$text = static function (DOMNodeList $nodes): array {
    return array_map(
        static fn(DOMNode $node): string => trim((string) $node->textContent),
        iterator_to_array($nodes)
    );
};

$desktopLeft = $text($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " nav-links-study ")]/a'));
$desktopRight = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " nav-links-transactional ")]/a');
$mobileWhere = $xpath->query('//div[@id="mobile-menu"]//a[normalize-space(.)="Где купить"]');

if ($desktopLeft !== array('Каталог', 'Рецепты', 'Где купить', 'Сотрудничество')) {
    fwrite(STDERR, 'Unexpected left navigation: ' . json_encode($desktopLeft, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
if (!$desktopRight instanceof DOMNodeList || $desktopRight->length !== 2) {
    fwrite(STDERR, 'Desktop right navigation must contain only cart and account actions.' . PHP_EOL);
    exit(1);
}
if (!$mobileWhere instanceof DOMNodeList || $mobileWhere->length !== 1) {
    fwrite(STDERR, 'Mobile navigation must keep one where-to-buy link.' . PHP_EOL);
    exit(1);
}

echo "Header where-to-buy placement verified\n";

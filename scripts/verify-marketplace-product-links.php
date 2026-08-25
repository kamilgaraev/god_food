<?php
declare(strict_types=1);

function home_url(string $path = ''): string {
    return 'https://example.test' . $path;
}

function esc_url(string $url): string {
    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_template_part(string $slug): void {
    // The contact section is outside the marketplace-card behavior under test.
}

function expect(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedLinks = array(
    array(
        'https://www.wildberries.ru/catalog/314543271/detail.aspx',
        'https://www.ozon.ru/product/kakao-poroshok-naturalnyy-bez-sahara-bez-aromatizatora-theobroma-pishcha-bogov-100-g-1617695779/',
    ),
    array(
        'https://www.wildberries.ru/catalog/676439280/detail.aspx',
        'https://www.ozon.ru/product/shokolad-gorkiy-naturalnyy-bez-sahara-s-vishney-i-zelenoy-grechkoy-theobroma-pishcha-bogov-30-g-3517113783/',
    ),
    array(
        'https://www.wildberries.ru/catalog/360846769/detail.aspx',
        'https://www.ozon.ru/product/shokolad-gorkiy-kuskovoy-naturalnyy-65-s-koritsey-theobroma-pishcha-bogov-100-g-1953234990/',
    ),
    array(
        'https://www.wildberries.ru/catalog/676439278/detail.aspx',
        'https://www.ozon.ru/product/shokolad-molochnyy-naturalnyy-na-kozem-moloke-bez-sahara-theobroma-pishcha-bogov-30-g-3517000735/',
    ),
);

ob_start();
require dirname(__DIR__) . '/wp-content/themes/theobroma/template-parts/pages/marketplace.php';
$html = (string) ob_get_clean();

if (PHP_SAPI !== 'cli' && isset($_GET['render'])) {
    echo '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/wp-content/themes/theobroma/style.css"></head><body>';
    echo $html;
    echo '</body></html>';
    exit;
}

$document = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($document);
$cards = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' market-product ')]");

expect($cards instanceof DOMNodeList && $cards->length === 4, 'Marketplace page must render exactly four product cards.');

foreach ($expectedLinks as $cardIndex => $expectedCardLinks) {
    $card = $cards->item($cardIndex);
    $links = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' market-product-actions ')]//a", $card);
    expect($links instanceof DOMNodeList && $links->length === 2, sprintf('Card %d must render one Wildberries and one Ozon link.', $cardIndex + 1));

    foreach ($expectedCardLinks as $linkIndex => $expectedHref) {
        $link = $links->item($linkIndex);
        expect($link instanceof DOMElement, sprintf('Card %d marketplace link %d is missing.', $cardIndex + 1, $linkIndex + 1));
        expect($link->getAttribute('href') === $expectedHref, sprintf('Card %d marketplace link %d points to the wrong product.', $cardIndex + 1, $linkIndex + 1));
        expect($link->getAttribute('target') === '_blank', sprintf('Card %d marketplace link %d must open in a new tab.', $cardIndex + 1, $linkIndex + 1));
        $rel = preg_split('/\s+/', trim($link->getAttribute('rel'))) ?: array();
        expect(in_array('noopener', $rel, true) && in_array('noreferrer', $rel, true), sprintf('Card %d marketplace link %d must isolate the external tab.', $cardIndex + 1, $linkIndex + 1));
        expect(trim($link->getAttribute('aria-label')) !== '', sprintf('Card %d marketplace link %d needs an accessible product label.', $cardIndex + 1, $linkIndex + 1));
    }
}

echo "Marketplace product links: OK\n";

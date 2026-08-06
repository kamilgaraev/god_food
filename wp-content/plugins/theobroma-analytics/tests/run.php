<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/AnalyticsConfig.php';
require_once dirname(__DIR__) . '/src/MetrikaRenderer.php';

use Theobroma\Analytics\AnalyticsConfig;
use Theobroma\Analytics\MetrikaRenderer;

$config = new AnalyticsConfig();
$safe = $config->sanitize(['counter_id' => ' 12345678 ', 'clickmap' => '1', 'webvisor' => '']);
if ($safe['counter_id'] !== '12345678' || !$safe['clickmap'] || $safe['webvisor']) {
    throw new RuntimeException('Analytics settings sanitization failed');
}
if ($config->sanitize(['counter_id' => '12<script>'])['counter_id'] !== '') {
    throw new RuntimeException('Invalid counter ID was accepted');
}

$renderer = new MetrikaRenderer();
if ($renderer->javascript($config->defaults()) !== '') {
    throw new RuntimeException('Metrika rendered without a counter ID');
}
$javascript = $renderer->javascript($safe);
foreach (['mc.yandex.ru/metrika/tag.js', 'ym(id,"init"', 'theobroma_cookie_notice_accepted', 'theobroma:cookie-consent'] as $needle) {
    if (!str_contains($javascript, $needle)) {
        throw new RuntimeException('Metrika bootstrap is incomplete: ' . $needle);
    }
}

echo "Analytics tests passed\n";

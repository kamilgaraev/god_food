<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';

$productionFiles = [
    dirname(__DIR__) . '/src/SeoDocument.php',
    dirname(__DIR__) . '/src/MetadataRenderer.php',
    dirname(__DIR__) . '/src/SchemaFactory.php',
];

foreach ($productionFiles as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, sprintf("FAIL SEO production file is missing: %s\n", basename($file)));
        exit(1);
    }
    require $file;
}

require __DIR__ . '/MetadataRendererTest.php';
require __DIR__ . '/SchemaFactoryTest.php';

$tests = [
    new Theobroma\Seo\Tests\MetadataRendererTest(),
    new Theobroma\Seo\Tests\SchemaFactoryTest(),
];

$failures = 0;
$count = 0;
foreach ($tests as $test) {
    foreach (get_class_methods($test) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $count++;
        try {
            $test->{$method}();
            echo sprintf("PASS %s::%s\n", $test::class, $method);
        } catch (Throwable $exception) {
            $failures++;
            echo sprintf("FAIL %s::%s: %s\n", $test::class, $method, $exception->getMessage());
        }
    }
}

echo sprintf("%d tests, %d failures\n", $count, $failures);
exit($failures === 0 ? 0 : 1);

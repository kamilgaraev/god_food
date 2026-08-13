<?php
declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'Theobroma\\OneC\\Tests\\' => __DIR__ . '/',
        'Theobroma\\OneC\\' => $root . '/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

$failures = [];
$count = 0;
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require_once $file;
    $class = 'Theobroma\\OneC\\Tests\\' . basename($file, '.php');
    $test = new $class();
    foreach (get_class_methods($test) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $count++;
        try {
            $test->{$method}();
            echo "PASS {$class}::{$method}\n";
        } catch (\Throwable $exception) {
            $failures[] = "FAIL {$class}::{$method}: {$exception->getMessage()}";
        }
    }
}
foreach ($failures as $failure) {
    fwrite(STDERR, $failure . PHP_EOL);
}
echo sprintf("%d tests, %d failures\n", $count, count($failures));
exit($failures === [] ? 0 : 1);

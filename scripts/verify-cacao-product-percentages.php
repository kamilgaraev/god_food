<?php
declare(strict_types=1);

final class WC_Product {}

require dirname(__DIR__) . '/wp-content/themes/theobroma/inc/homepage.php';

function assert_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true));
    }
}

$groups = array(
    80 => array('representative' => 'product-80'),
    59 => array('representative' => 'product-59'),
    70 => array('representative' => 'product-70'),
    65 => array('representative' => 'product-65'),
    68 => array('representative' => 'product-68'),
);
$profiles = theobroma_cacao_profiles();

$options = theobroma_home_cacao_options($groups, $profiles);

assert_same(array(59, 65, 68, 70, 80), array_keys($options), 'Every catalog cacao group must have one selector option in numeric order.');
foreach ($options as $percentage => $option) {
    assert_same($percentage, $option['percentage'], 'The visible percentage must match the product percentage.');
    assert_same($groups[$percentage], $option['group'], 'Each percentage must retain its matching product group.');
    assert_same($profiles[$percentage]['label'], $option['label'], 'Each percentage must retain its matching taste profile.');
}

fwrite(STDOUT, "Cacao product percentage mapping verified.\n");

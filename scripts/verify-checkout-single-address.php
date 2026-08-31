<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../wp-content/themes/theobroma/functions.php');
if (!is_string($source)) {
    throw new RuntimeException('Unable to read theme functions.');
}

if (!str_contains($source, "add_filter('woocommerce_cart_needs_shipping_address', '__return_false')")) {
    throw new RuntimeException('Checkout must disable the separate shipping-address form.');
}

echo "Checkout single-address verification passed.\n";

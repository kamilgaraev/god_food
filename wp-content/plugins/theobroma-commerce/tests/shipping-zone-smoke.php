<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$zone = new WC_Shipping_Zone(0);
$ids = array_map(static fn (WC_Shipping_Method $method): string => $method->id, $zone->get_shipping_methods(true));
if (!in_array('theobroma_cdek', $ids, true)) {
    throw new RuntimeException('CDEK method is missing from the default shipping zone');
}

echo "CDEK default-zone smoke passed\n";

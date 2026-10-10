<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

if (!class_exists('WC_Coupon')) {
    fwrite(STDERR, "WooCommerce is unavailable.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$expires = current_datetime()->modify('+14 days')->setTime(23, 59, 59)->getTimestamp();
$definitions = array(
    array('code' => 'THEO-TEST-10', 'type' => 'percent', 'amount' => 10),
    array('code' => 'THEO-TEST-100', 'type' => 'fixed_cart', 'amount' => 100),
);

foreach ($definitions as $definition) {
    $code = $definition['code'];
    $existing_id = wc_get_coupon_id_by_code($code);
    if ($existing_id) {
        $existing = new WC_Coupon($existing_id);
        if ($existing->get_discount_type() !== $definition['type']
            || (float) $existing->get_amount() !== (float) $definition['amount']) {
            fwrite(STDERR, "Existing coupon {$code} has different terms; no changes made.\n");
            exit(1);
        }
        echo "{$code}: already exists (#{$existing_id}).\n";
        continue;
    }

    if (!$apply) {
        echo "{$code}: would create {$definition['type']} {$definition['amount']}.\n";
        continue;
    }

    $coupon = new WC_Coupon();
    $coupon->set_code($code);
    $coupon->set_discount_type($definition['type']);
    $coupon->set_amount((string) $definition['amount']);
    $coupon->set_description('Тестовый промокод для проверки оформления заказа. Действует 14 дней, максимум 10 заказов.');
    $coupon->set_usage_limit(10);
    $coupon->set_usage_limit_per_user(1);
    $coupon->set_individual_use(false);
    $coupon->set_free_shipping(false);
    $coupon->set_date_expires($expires);
    $id = $coupon->save();
    if (!$id) {
        fwrite(STDERR, "Could not create coupon {$code}.\n");
        exit(1);
    }
    echo "{$code}: created (#{$id}), expires " . wp_date('Y-m-d H:i', $expires) . ".\n";
}

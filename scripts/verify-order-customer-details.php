<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function wc_ship_to_billing_address_only(): bool
{
    return false;
}

function esc_html__(string $text, string $domain = ''): string
{
    return $text === 'N/A' ? 'Н/Д' : $text;
}

function esc_html_e(string $text, string $domain = ''): void
{
    echo htmlspecialchars($text, ENT_QUOTES);
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES);
}

function wp_kses_post(string $html): string
{
    return $html;
}

function do_action(string $hook, mixed ...$args): void
{
}

final class OrderCustomerDetailsFixture
{
    public function __construct(private string $shippingAddress)
    {
    }

    public function needs_shipping_address(): bool
    {
        return true;
    }

    public function get_formatted_billing_address(string $empty = ''): string
    {
        return 'Казань, Спартаковская улица, 12';
    }

    public function get_formatted_shipping_address(string $empty = ''): string
    {
        return $this->shippingAddress !== '' ? $this->shippingAddress : $empty;
    }

    public function get_billing_phone(): string
    {
        return '+7 987 219-89-86';
    }

    public function get_billing_email(): string
    {
        return 'buyer@example.test';
    }

    public function get_shipping_phone(): string
    {
        return '';
    }
}

$template = __DIR__ . '/../wp-content/themes/theobroma/woocommerce/order/order-details-customer.php';
if (!is_file($template)) {
    throw new RuntimeException('Order customer details override must exist.');
}

$render = static function (string $shippingAddress) use ($template): string {
    $order = new OrderCustomerDetailsFixture($shippingAddress);
    ob_start();
    require $template;
    return (string) ob_get_clean();
};

$withoutShipping = $render('');
if (!str_contains($withoutShipping, 'Платёжный адрес')) {
    throw new RuntimeException('Billing address card must remain visible.');
}
if (str_contains($withoutShipping, 'Адрес доставки') || str_contains($withoutShipping, 'Н/Д')) {
    throw new RuntimeException('Empty shipping address card must not be rendered.');
}
if (!str_contains($withoutShipping, 'woocommerce-columns--1')) {
    throw new RuntimeException('A single billing card must use the full-width layout.');
}

$withShipping = $render('Пункт Ozon, Спартаковская улица, 14');
if (!str_contains($withShipping, 'Платёжный адрес') || !str_contains($withShipping, 'Адрес доставки')) {
    throw new RuntimeException('Both address cards must be rendered when shipping address exists.');
}
if (!str_contains($withShipping, 'Пункт Ozon, Спартаковская улица, 14')) {
    throw new RuntimeException('Shipping address content must be preserved.');
}

echo "Order customer details verification passed.\n";

<?php
declare(strict_types=1);

namespace {
    $scenario = $argv[1] ?? '';
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Theobroma\\OneC\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });

    final class WC_Order
    {
        public function get_id(): int { return 548; }
        public function get_order_number(): string { return '548'; }
        public function get_items(string $type): array { return []; }
        public function get_refunds(): array { return []; }
        public function get_date_created(): mixed { return null; }
        public function get_date_paid(): mixed { return null; }
        public function get_shipping_address_1(): string { return ''; }
        public function get_shipping_address_2(): string { return ''; }
        public function get_billing_address_1(): string { return ''; }
        public function get_billing_address_2(): string { return ''; }
        public function get_currency(): string { return 'RUB'; }
        public function get_total(): string { return '297.00'; }
        public function get_status(): string { return 'processing'; }
        public function is_paid(): bool { return true; }
        public function has_status(string $status): bool { return false; }
        public function get_formatted_billing_full_name(): string { return 'Test Buyer'; }
        public function get_billing_email(): string { return 'buyer@example.test'; }
        public function get_billing_phone(): string { return '+7'; }
        public function get_payment_method_title(): string { return 'Card'; }
        public function get_meta(string $key, bool $single): string { return ''; }
        public function get_shipping_total(): string { return '0.00'; }
        public function get_coupon_codes(): array { return []; }
        public function save(): never { throw new RuntimeException('Download must not save orders'); }
        public function update_meta_data(string $key, mixed $value): never { throw new RuntimeException('Download must not acknowledge orders'); }
    }
    final class WC_Order_Refund {}
    function wc_format_decimal(string $value, int $decimals): string { return number_format((float) $value, $decimals, '.', ''); }
}

namespace Theobroma\OneC\Admin {
    function current_user_can(string $capability): bool
    {
        if ($capability !== 'manage_woocommerce') { throw new \RuntimeException('Wrong capability'); }
        return $GLOBALS['scenario'] !== 'forbidden';
    }
    function check_admin_referer(string $action): void
    {
        if ($action !== OrderXmlDownload::ACTION) { throw new \RuntimeException('Wrong nonce action'); }
        if ($GLOBALS['scenario'] === 'bad_nonce') { wp_die('Invalid nonce', '', ['response' => 403]); }
    }
    function wc_get_order(int $id): mixed
    {
        if ($id !== 548 || in_array($GLOBALS['scenario'], ['forbidden', 'bad_nonce', 'invalid_id', 'negative_id', 'array_id'], true)) {
            throw new \RuntimeException('Order lookup before request validation');
        }
        return match ($GLOBALS['scenario']) { 'missing' => false, 'refund' => new \WC_Order_Refund(), default => new \WC_Order() };
    }
    function wp_die(string $message, string $title, array $arguments): never
    {
        echo json_encode(['status' => $arguments['response'], 'message' => $message], JSON_THROW_ON_ERROR);
        exit;
    }
    function nocache_headers(): void { fwrite(STDERR, "nocache_headers\n"); }
    function header(string $header): void { fwrite(STDERR, $header . "\n"); }
    function add_action(string $hook, callable $callback): void { $GLOBALS['hooks'][] = $hook; }
}

namespace {
    $_POST['order_id'] = match ($scenario) { 'invalid_id' => '548junk', 'negative_id' => '-548', 'array_id' => ['548'], default => '548' };
    $handler = new \Theobroma\OneC\Admin\OrderXmlDownload();
    if ($scenario === 'register') {
        $GLOBALS['hooks'] = [];
        $handler->register();
        echo json_encode($GLOBALS['hooks'], JSON_THROW_ON_ERROR);
    } else {
        $handler->download();
    }
}

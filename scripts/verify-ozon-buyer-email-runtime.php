<?php
/** Exercise the normal Ozon order handler in staging with in-memory orders and intercepted HTTP. */
declare(strict_types=1);
require '/var/www/html/wp-load.php';
if (getenv('THEOBROMA_ENVIRONMENT') !== 'staging') {
    throw new RuntimeException('This verification requires staging.');
}

function ozon_email_check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class OzonEmailVerificationOrder extends WC_Order {
    public array $verificationNotes = [];
    public int $verificationSaves = 0;
    private WC_Order_Item_Shipping $verificationShipping;
    private WC_Order_Item_Product $verificationLine;

    public function __construct() {
        parent::__construct();
        $this->set_currency('RUB');
        $this->set_payment_method('cod');
        $this->set_billing_first_name('Иван');
        $this->set_billing_last_name('Иванов');
        $this->set_billing_email('buyer@example.test');
        $this->verificationShipping = new WC_Order_Item_Shipping();
        $this->verificationShipping->set_method_id('theobroma_ozon');
        $this->verificationLine = new class extends WC_Order_Item_Product {
            public function get_product() {
                $product = new WC_Product_Simple();
                $product->update_meta_data('_theobroma_ozon_sku', '101');
                return $product;
            }
        };
        $this->verificationLine->set_quantity(1);
        $this->verificationLine->set_total('100');
        $this->update_meta_data('_theobroma_ozon_create_payload', json_encode([
            'buyer' => ['first_name' => 'Устаревшее', 'phone' => '79990000000'],
            'recipient' => ['recipient_phone' => '79990000000'],
            'delivery' => ['pick_up' => ['delivery_point_id' => 42]],
            'delivery_schema' => 'FBS',
            'splits' => [[
                'warehouse_id' => 77,
                'delivery_method' => ['price' => ['amount' => '50', 'currency' => 'RUB']],
                'items' => [['sku' => 101, 'quantity' => 1]],
            ]],
        ], JSON_UNESCAPED_UNICODE));
    }
    public function get_items($types = 'line_item') {
        return $types === 'shipping' ? [$this->verificationShipping] : [$this->verificationLine];
    }
    public function add_order_note($note, $is_customer_note = 0, $added_by_user = false, $meta_data = []) {
        $this->verificationNotes[] = $note;
        return 0;
    }
    public function save() {
        $this->verificationSaves++;
        return 0;
    }
}

add_filter('pre_option_theobroma_ozon_oauth_tokens', static fn() => [
    'token' => 'verification-token', 'expires_at' => time() + 3600,
]);
$requests = [];
add_filter('pre_http_request', static function ($preempt, $args, $url) use (&$requests) {
    if ($url !== 'https://api-seller.ozon.ru/v2/order/create') {
        return new WP_Error('verification_blocked', 'External HTTP is blocked by this verification.');
    }
    $requests[] = json_decode((string) $args['body'], true, 512, JSON_THROW_ON_ERROR);
    return ['response' => ['code' => 200], 'headers' => [], 'body' => json_encode([
        'result' => ['order_number' => 'VERIFICATION-ORDER', 'postings' => [['posting_number' => 'VERIFICATION-POSTING']]],
    ])];
}, PHP_INT_MAX, 3);

$lifecycle = new Theobroma\Commerce\Orders\OzonOrderLifecycle();
$order = new OzonEmailVerificationOrder();
$lifecycle->createCodShipment(0, [], $order);
ozon_email_check(count($requests) === 1, 'The normal handler did not create the mocked shipment.');
ozon_email_check($requests[0]['buyer']['email'] === 'buyer@example.test', 'Saved order email was not sent.');
ozon_email_check($requests[0]['buyer']['first_name'] === 'Иван', 'Current buyer name was not sent.');
ozon_email_check($order->get_meta('_theobroma_ozon_order_number') === 'VERIFICATION-ORDER', 'Order number was not saved.');
ozon_email_check($order->get_meta('_theobroma_ozon_postings') === ['VERIFICATION-POSTING'], 'Posting number was not saved.');
ozon_email_check($order->verificationSaves === 1, 'The shipment result was not saved once.');
$lifecycle->createCodShipment(0, [], $order);
ozon_email_check(count($requests) === 1, 'Repeated callback attempted a duplicate shipment.');

$missingEmail = new OzonEmailVerificationOrder();
$missingEmail->set_billing_email('');
$lifecycle->createCodShipment(0, [], $missingEmail);
ozon_email_check(count($requests) === 1, 'Missing email reached the provider.');
ozon_email_check(count($missingEmail->verificationNotes) === 1, 'Missing email has no diagnostic note.');

$legacy = new OzonEmailVerificationOrder();
$legacy->set_billing_email('');
$legacy->update_meta_data('_theobroma_ozon_order_id', 'EXISTING-LEGACY');
$lifecycle->createCodShipment(0, [], $legacy);
ozon_email_check(count($requests) === 1 && $legacy->verificationNotes === [], 'An existing legacy shipment was retried.');
echo "Staging Ozon handler passed: email, identifiers, duplicate callback, missing email, legacy shipment. No live provider requests or order writes.\n";

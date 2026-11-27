<?php
/** Verify the configured origin through quote, shipping metadata and shipment payload without external writes. */
declare(strict_types=1);
require '/var/www/html/wp-load.php';
if (getenv('THEOBROMA_ENVIRONMENT') !== 'staging') throw new RuntimeException('Staging only.');
$settings = (array) get_option('theobroma_commerce_settings', []);
if (($settings['cdek_shipment_point'] ?? '') !== 'MSK362') throw new RuntimeException('Expected staging origin MSK362.');
$tokens = new class implements Theobroma\Commerce\Integrations\Cdek\TokenStore {
    public function get(): ?array { return ['token' => 'verification', 'expires_at' => time() + 3600]; }
    public function put(string $token, int $expiresAt): void {}
    public function forget(): void {}
};
$transport = new Theobroma\Commerce\Infrastructure\WpTransport(static function ($url, $args) {
    $path = parse_url($url, PHP_URL_PATH);
    if ($path === '/v2/deliverypoints') $body = [['code' => 'MSK362', 'is_reception' => true, 'location' => ['city_code' => 44]]];
    elseif ($path === '/v2/calculator/tarifflist') {
        $request = json_decode($args['body'], true);
        if (($request['shipment_point'] ?? '') !== 'MSK362' || ($request['from_location']['code'] ?? 0) !== 44) throw new RuntimeException('Calculator origin mismatch.');
        $body = ['tariff_codes' => [
            ['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => 195],
            ['tariff_code' => 137, 'delivery_mode' => 3, 'delivery_sum' => 445],
            ['tariff_code' => 138, 'delivery_mode' => 2, 'delivery_sum' => 150],
            ['tariff_code' => 139, 'delivery_mode' => 1, 'delivery_sum' => 200],
            ['tariff_code' => 358, 'delivery_mode' => 4, 'delivery_sum' => 100],
        ]];
    } else throw new RuntimeException('Unexpected external request.');
    return ['response' => ['code' => 200], 'headers' => [], 'body' => json_encode($body)];
});
$client = new Theobroma\Commerce\Integrations\Cdek\CdekClient($transport, $tokens, 'verification', 'verification');
foreach (['pickup' => 136] as $kind => $tariff) {
    $package = (new Theobroma\Commerce\Shipping\CdekPackageBuilder(0, 'MSK362'))->build(['city' => 'Москва'], [['quantity' => 1, 'weight_kg' => .2]]);
    $package['to_location']['code'] = 44;
    $quote = (new Theobroma\Commerce\Checkout\CdekCheckoutService($client))->quote($package, $kind);
    $memory = [];
    $store = new Theobroma\Commerce\Checkout\DeliverySelectionStore(
        static function ($key) use (&$memory) { return $memory[$key] ?? null; },
        static function ($key, $value) use (&$memory) { $memory[$key] = $value; },
        static function ($key) use (&$memory) { unset($memory[$key]); }
    );
    $store->save(Theobroma\Commerce\Checkout\DeliverySelection::fromArray([
        'provider' => 'cdek', 'kind' => $kind, 'fingerprint' => 'verification',
        'point' => ['id' => 'MSK1'], 'quote' => ['cost' => $quote->cost(), 'label' => $quote->label()],
        'create_payload' => $quote->createPayload(),
    ]));
    $rate = (new Theobroma\Commerce\Checkout\DeliveryRateResolver($store))->resolve('cdek', 'verification');
    $shipping = new WC_Order_Item_Shipping();
    foreach ($rate['meta_data'] as $key => $value) $shipping->add_meta_data($key, $value, true);
    $payload = (new Theobroma\Commerce\Orders\CdekOrderPayloadFactory(44, 'Legacy', (string) $shipping->get_meta('theobroma_cdek_shipment_point')))->build([
        'number' => 'VERIFICATION', 'tariff_code' => (int) $shipping->get_meta('theobroma_tariff_code'),
        'delivery_kind' => $kind, 'pickup_code' => 'MSK1',
        'recipient' => ['name' => 'Иван Иванов', 'phone' => '+79990000000'],
        'destination' => ['city' => 'Москва', 'address' => 'Тестовая, 1'],
        'items' => [['sku' => 'TEST', 'name' => 'Шоколад', 'quantity' => 1, 'weight_g' => 200, 'unit_price' => 100]],
    ]);
    if ($payload['tariff_code'] !== $tariff || ($payload['shipment_point'] ?? '') !== 'MSK362' || isset($payload['from_location'])) throw new RuntimeException('Shipment origin mismatch.');
    if (isset($payload['delivery_point']) !== ($kind === 'pickup')) throw new RuntimeException('Destination kind mismatch.');
    echo $kind . ': tariff ' . $tariff . ', origin MSK362, quote and shipping metadata verified.' . PHP_EOL;
}
echo 'No live carrier orders or WooCommerce orders were created.' . PHP_EOL;

<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$products = wc_get_products(['status' => 'publish', 'limit' => 1]);
$product = $products[0] ?? null;
if (!$product instanceof WC_Product) {
    throw new RuntimeException('A published product is required for the email smoke test.');
}

$recipient = 'qa-order@theobroma.local';
$order = wc_create_order();
if (!$order instanceof WC_Order) {
    throw new RuntimeException('Could not create the temporary email smoke order.');
}

$messageId = '';
try {
    $order->add_product($product, 1);
    $order->set_billing_first_name('QA');
    $order->set_billing_last_name('Theobroma');
    $order->set_billing_email($recipient);
    $order->set_payment_method('cod');
    $order->calculate_totals();
    $order->save();

    $emails = WC()->mailer()->get_emails();
    $email = $emails['WC_Email_Customer_Processing_Order'] ?? null;
    if (!$email instanceof WC_Email_Customer_Processing_Order) {
        throw new RuntimeException('WooCommerce customer processing email is unavailable.');
    }
    $email->trigger($order->get_id(), $order);

    $matched = null;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $response = wp_remote_get('http://mailpit:8025/api/v1/messages?start=0&limit=50', ['timeout' => 3]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $payload = json_decode(wp_remote_retrieve_body($response), true);
            foreach (($payload['messages'] ?? []) as $message) {
                $recipients = array_map(
                    static fn (array $address): string => (string) ($address['address'] ?? $address['Address'] ?? ''),
                    (array) ($message['to'] ?? $message['To'] ?? [])
                );
                if (in_array($recipient, $recipients, true)) {
                    $matched = $message;
                    break 2;
                }
            }
        }
        usleep(100000);
    }

    $messageId = (string) ($matched['id'] ?? $matched['ID'] ?? '');
    if (!is_array($matched) || $messageId === '') {
        throw new RuntimeException('Mailpit did not receive the WooCommerce customer email.');
    }
    $response = wp_remote_get('http://mailpit:8025/api/v1/message/' . rawurlencode($messageId), ['timeout' => 3]);
    $message = json_decode(wp_remote_retrieve_body($response), true);
    $content = (string) ($message['text'] ?? $message['Text'] ?? '') . ' ' . (string) ($message['html'] ?? $message['HTML'] ?? '');
    if (!str_contains($content, (string) $order->get_order_number()) || !str_contains($content, $product->get_name())) {
        throw new RuntimeException('WooCommerce email is missing the order number or product details.');
    }

    echo 'WooCommerce customer email reached Mailpit with order details' . PHP_EOL;
} finally {
    if ($messageId !== '') {
        wp_remote_request('http://mailpit:8025/api/v1/messages', [
            'method' => 'DELETE',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['IDs' => [$messageId]]),
            'timeout' => 3,
        ]);
    }
    $order->delete(true);
}

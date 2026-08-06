<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Rest;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore;
use Theobroma\Commerce\Orders\CdekStatusSynchronizer;
use Theobroma\Commerce\Orders\WooShipmentOrder;

final class CdekWebhookController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('theobroma-commerce/v1', '/cdek/webhook', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'ORDER_STATUS') {
            return new \WP_REST_Response(['accepted' => true], 200);
        }

        $uuid = trim((string) ($payload['uuid'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return new \WP_REST_Response(['accepted' => false], 400);
        }

        $orders = wc_get_orders([
            'limit' => 2,
            'return' => 'objects',
            'meta_query' => [[
                'key' => '_theobroma_cdek_uuid',
                'value' => $uuid,
                'compare' => '=',
            ]],
        ]);
        if (count($orders) !== 1 || !$orders[0] instanceof \WC_Order) {
            return new \WP_REST_Response(['accepted' => true], 200);
        }

        $settings = (array) get_option('theobroma_commerce_settings', []);
        $clientId = (string) ($settings['cdek_client_id'] ?? '');
        $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET')
            ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET')
            : (string) ($settings['cdek_client_secret'] ?? '');
        if ($clientId === '' || $secret === '') {
            return new \WP_REST_Response(['accepted' => false], 503);
        }

        try {
            $client = new CdekClient(new WpTransport(), new WordPressTokenStore(), $clientId, $secret);
            $status = (new CdekStatusSynchronizer($client))->sync(new WooShipmentOrder($orders[0]), $uuid);
            return new \WP_REST_Response(['accepted' => true, 'status' => $status], 200);
        } catch (\Throwable $exception) {
            wc_get_logger()->error($exception->getMessage(), ['source' => 'theobroma-cdek-webhook', 'order_id' => $orders[0]->get_id()]);
            return new \WP_REST_Response(['accepted' => false], 502);
        }
    }
}

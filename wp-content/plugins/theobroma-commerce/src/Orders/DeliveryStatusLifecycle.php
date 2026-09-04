<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Integrations\Cdek\CdekClient;

final class DeliveryStatusLifecycle
{
    private const HOOK = 'theobroma_delivery_status_poll';

    public function register(): void
    {
        add_action('init', [$this, 'registerStatus']);
        add_filter('wc_order_statuses', [$this, 'statuses']);
        add_filter('woocommerce_order_is_paid_statuses', [$this, 'includeShipped']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'includeShipped']);
        add_action('action_scheduler_init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'poll']);
    }

    public function registerStatus(): void
    {
        register_post_status('wc-shipped', [
            'label' => 'Передано в доставку', 'public' => true,
            'exclude_from_search' => false, 'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Передано в доставку <span class="count">(%s)</span>', 'Передано в доставку <span class="count">(%s)</span>', 'theobroma-commerce'),
        ]);
    }

    public function statuses(array $statuses): array
    {
        $result = [];
        foreach ($statuses as $key => $label) {
            $result[$key] = $label;
            if ($key === 'wc-processing') {
                $result['wc-shipped'] = 'Передано в доставку';
            }
        }
        return $result;
    }

    public function includeShipped(array $statuses): array
    {
        return array_values(array_unique(array_merge($statuses, ['shipped'])));
    }

    public function schedule(): void
    {
        if (function_exists('as_next_scheduled_action') && !as_next_scheduled_action(self::HOOK, [], 'theobroma')) {
            as_schedule_recurring_action(time() + 60, 900, self::HOOK, [], 'theobroma', true);
        }
    }

    /** Unknown, canceled and partially delivered shipments never complete an order. */
    public static function nextStatus(array $states): ?string
    {
        if ($states === []) {
            return null;
        }
        if (count(array_filter($states, static fn ($s) => $s === 'delivered')) === count($states)) {
            return 'completed';
        }
        if (count(array_filter($states, static fn ($s) => in_array($s, ['delivering', 'delivered'], true))) === count($states)) {
            return 'shipped';
        }
        return null;
    }

    public function poll(): void
    {
        $page = max(1, (int) get_option('theobroma_delivery_poll_page', 1));
        $batch = wc_get_orders(['status' => ['processing', 'shipped'], 'limit' => 20, 'page' => $page, 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC']);
        $settings = (array) get_option('theobroma_commerce_settings', []);
        foreach ($batch->orders as $order) {
            try {
                $states = $this->states($order, $settings);
                $next = self::nextStatus($states);
                // Reload before updating: do not undo an administrator's cancellation.
                $fresh = wc_get_order($order->get_id());
                if (!$fresh || !in_array($fresh->get_status(), ['processing', 'shipped'], true)) {
                    continue;
                }
                if ($next !== null && $fresh->get_status() !== $next) {
                    $fresh->update_status($next, $next === 'completed' ? 'Перевозчик подтвердил вручение всех отправлений.' : 'Перевозчик подтвердил передачу всех отправлений в доставку.');
                }
            } catch (\Throwable $error) {
                wc_get_logger()->warning('Delivery tracking failed', ['source' => 'theobroma-delivery-tracking', 'order_id' => $order->get_id(), 'reason' => OzonFailureReason::describe($error)]);
            }
        }
        update_option('theobroma_delivery_poll_page', $page < (int) $batch->max_num_pages ? $page + 1 : 1, false);
    }

    private function states(\WC_Order $order, array $settings): array
    {
        $methods = [];
        foreach ($order->get_items('shipping') as $item) {
            $methods[] = $item->get_method_id();
        }
        $methods = array_values(array_unique($methods));
        // Mixed providers require a separate per-package reconciliation strategy.
        if (count($methods) !== 1) {
            return [];
        }
        if ($methods[0] === 'theobroma_ozon') {
            $postings = (array) $order->get_meta('_theobroma_ozon_postings', true);
            if ($postings === []) {
                return [];
            }
            $payload = (new OzonOrderPayloadResolver())->resolve($order->get_meta('_theobroma_ozon_create_payload', true), $order->get_items('shipping'));
            $schema = $payload['delivery_schema'] ?? '';
            if (!in_array($schema, ['FBO', 'FBS'], true)) {
                return [];
            }
            $client = (new OzonClientFactory(new WpTransport(), new WordPressTokenStore()))->clientFromSettings($settings);
            $states = [];
            foreach ($postings as $number) {
                if (!is_string($number) || trim($number) === '') {
                    return [];
                }
                $data = $schema === 'FBO' ? $client->fboPostingGet(['posting_number' => $number]) : $client->fbsPostingGet(['posting_number' => $number]);
                if (($data['posting_number'] ?? '') !== $number) {
                    throw new \RuntimeException('Posting identity mismatch');
                }
                $states[] = strtolower((string) ($data['status'] ?? ''));
            }
            return $states;
        }
        if ($methods[0] === 'theobroma_cdek') {
            $uuid = (string) $order->get_meta('_theobroma_cdek_uuid', true);
            if ($uuid === '') {
                return [];
            }
            $secret = defined('THEOBROMA_CDEK_CLIENT_SECRET') ? (string) constant('THEOBROMA_CDEK_CLIENT_SECRET') : (string) ($settings['cdek_client_secret'] ?? '');
            $client = new CdekClient(new WpTransport(), new \Theobroma\Commerce\Integrations\Cdek\WordPressTokenStore(), (string) ($settings['cdek_client_id'] ?? ''), $secret);
            $status = (new CdekStatusSynchronizer($client))->sync(new WooShipmentOrder($order), $uuid);
            if ($status === 'DELIVERED') {
                return ['delivered'];
            }
            return in_array($status, ['RECEIVED_AT_SHIPMENT_WAREHOUSE', 'DEPARTED', 'ARRIVED_AT_DESTINATION_CITY', 'ACCEPTED_AT_DELIVERY_WAREHOUSE', 'TAKEN_BY_COURIER'], true) ? ['delivering'] : [];
        }
        return [];
    }
}

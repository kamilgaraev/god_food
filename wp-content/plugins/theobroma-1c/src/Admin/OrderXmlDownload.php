<?php
declare(strict_types=1);

namespace Theobroma\OneC\Admin;

use Theobroma\OneC\CommerceMl\OrderWriter;
use Theobroma\OneC\Orders\WooOrderMapper;

final class OrderXmlDownload
{
    public const ACTION = 'theobroma_1c_download_order_xml';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'download']);
    }

    public function download(): never
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Недостаточно прав для скачивания XML заказа.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $rawId = $_POST['order_id'] ?? null;
        $orderId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($orderId === false) {
            wp_die('Укажите корректный ID заказа.', '', ['response' => 400]);
        }
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            wp_die('Заказ не найден.', '', ['response' => 404]);
        }

        $xml = (new OrderWriter())->write([(new WooOrderMapper())->map($order)]);

        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="theobroma-order-' . $orderId . '.xml"');
        header('X-Content-Type-Options: nosniff');
        echo $xml;
        exit;
    }
}

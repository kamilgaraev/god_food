<?php

declare(strict_types=1);

namespace Theobroma\PhotoShowcases;

final class DefaultImages
{
    /** @return list<int> */
    public function ids(int $limit = 5): array
    {
        if ($limit <= 0 || !function_exists('wc_get_products')) {
            return array();
        }

        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => max($limit * 2, 8),
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'return' => 'objects',
        ));
        $result = array();
        $seen = array();

        foreach ($products as $product) {
            if (!is_object($product) || !method_exists($product, 'get_image_id')) {
                continue;
            }

            $attachmentId = (int) $product->get_image_id();
            if ($attachmentId <= 0 || isset($seen[$attachmentId]) || !wp_attachment_is_image($attachmentId)) {
                continue;
            }

            $result[] = $attachmentId;
            $seen[$attachmentId] = true;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }
}

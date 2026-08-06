<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Wishlist;

final class WishlistController
{
    public function register(): void
    {
        add_filter('theobroma_wishlist_ids', [$this, 'initialIds']);
        add_action('wp_ajax_theobroma_wishlist_items', [$this, 'items']);
        add_action('wp_ajax_nopriv_theobroma_wishlist_items', [$this, 'items']);
        add_action('wp_ajax_theobroma_wishlist_save', [$this, 'save']);
    }

    /** @param array<mixed> $default @return list<int> */
    public function initialIds(array $default): array
    {
        if (!is_user_logged_in()) {
            return $default;
        }
        return $this->validIds((array) get_user_meta(get_current_user_id(), WishlistStore::META_KEY, true));
    }

    public function items(): void
    {
        check_ajax_referer('theobroma_commerce', 'nonce');
        $ids = is_user_logged_in()
            ? (array) get_user_meta(get_current_user_id(), WishlistStore::META_KEY, true)
            : $this->postedIds();
        $ids = $this->validIds($ids);

        $items = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
            $items[] = [
                'id' => $id,
                'title' => wp_strip_all_tags($product->get_name()),
                'url' => $product->get_permalink(),
                'image' => $image ?: wc_placeholder_img_src('woocommerce_thumbnail'),
                'price' => wp_strip_all_tags(wc_price((float) $product->get_price())),
            ];
        }
        wp_send_json_success(['ids' => $ids, 'items' => $items]);
    }

    public function save(): void
    {
        check_ajax_referer('theobroma_commerce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Authentication required'], 401);
        }
        $ids = $this->validIds($this->postedIds());
        update_user_meta(get_current_user_id(), WishlistStore::META_KEY, $ids);
        wp_send_json_success(['ids' => $ids]);
    }

    /** @return list<mixed> */
    private function postedIds(): array
    {
        $decoded = json_decode((string) wp_unslash($_POST['ids'] ?? '[]'), true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param iterable<mixed> $ids @return list<int> */
    private function validIds(iterable $ids): array
    {
        return (new WishlistStore())->normalize($ids, static function (int $id): bool {
            $product = wc_get_product($id);
            return $product instanceof \WC_Product && $product->is_visible();
        });
    }
}

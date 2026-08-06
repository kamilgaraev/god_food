<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Loyalty;

final class LoyaltyAccountEndpoint
{
    private const SLUG = 'bonuses';
    private const PAGE_SIZE = 20;

    private LoyaltyStore $store;

    public function __construct(?LoyaltyStore $store = null)
    {
        if (!$store instanceof LoyaltyStore) {
            global $wpdb;
            $store = new WpdbLoyaltyStore($wpdb);
        }
        $this->store = $store;
    }

    public function register(): void
    {
        add_action('init', [$this, 'addEndpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'menuItems'], 50);
        add_action('woocommerce_account_' . self::SLUG . '_endpoint', [$this, 'render']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function addEndpoint(): void
    {
        add_rewrite_endpoint(self::SLUG, EP_ROOT | EP_PAGES);
    }

    /** @param array<string,string> $items @return array<string,string> */
    public function menuItems(array $items): array
    {
        $result = [];
        $inserted = false;
        foreach ($items as $key => $label) {
            $result[$key] = $label;
            if ($key === 'orders') {
                $result[self::SLUG] = 'Бонусы';
                $inserted = true;
            }
        }
        if (!$inserted) {
            $result[self::SLUG] = 'Бонусы';
        }

        return $result;
    }

    /** @return array<int,LoyaltyEntry> */
    public function historyForUser(int $userId, int $limit, int $offset): array
    {
        if ($userId < 1) {
            return [];
        }

        return $this->store->history($userId, max(1, min(100, $limit)), max(0, $offset));
    }

    public function render(): void
    {
        $userId = get_current_user_id();
        if ($userId < 1) {
            return;
        }

        $page = max(1, absint($_GET['bonus-page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $entries = $this->historyForUser($userId, self::PAGE_SIZE + 1, $offset);
        $hasNext = count($entries) > self::PAGE_SIZE;
        $entries = array_slice($entries, 0, self::PAGE_SIZE);
        $balance = $this->store->balance($userId);
        $availableKopecks = (int) ($balance['available_kopecks'] ?? 0);
        $reservedKopecks = max(0, (int) ($balance['reserved_kopecks'] ?? 0));
        $template = THEOBROMA_COMMERCE_PATH . 'templates/myaccount/bonuses.php';
        if (is_file($template)) {
            include $template;
        }
    }
}

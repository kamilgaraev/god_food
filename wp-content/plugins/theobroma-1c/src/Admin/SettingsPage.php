<?php
declare(strict_types=1);

namespace Theobroma\OneC\Admin;

use Theobroma\OneC\Settings\Settings;

final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void
    {
        add_submenu_page('woocommerce', 'Интеграция с 1С', 'Интеграция с 1С', 'manage_woocommerce', 'theobroma-1c', [$this, 'render']);
    }

    public function settings(): void
    {
        register_setting('theobroma_1c', Settings::OPTION, ['sanitize_callback' => [$this, 'sanitize']]);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function sanitize(array $input): array
    {
        $old = (new Settings())->get();
        $password = trim((string) ($input['password'] ?? ''));
        return [
            'enabled' => !empty($input['enabled']),
            'username' => sanitize_user((string) ($input['username'] ?? ''), true),
            'password_hash' => $password !== '' ? wp_hash_password($password) : $old['password_hash'],
            'batch_size' => min(100, max(1, (int) ($input['batch_size'] ?? 50))),
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = (new Settings())->get();
        echo '<div class="wrap"><h1>Интеграция с 1С</h1>';
        echo '<p>URL обмена: <code>' . esc_html(home_url('/theobroma-1c/exchange')) . '</code></p>';
        $this->diagnostics();
        echo '<form method="post" action="options.php">';
        settings_fields('theobroma_1c');
        echo '<table class="form-table">';
        echo '<tr><th>Включить обмен</th><td><input type="checkbox" name="' . Settings::OPTION . '[enabled]" value="1" ' . checked($settings['enabled'], true, false) . '></td></tr>';
        echo '<tr><th>Логин</th><td><input name="' . Settings::OPTION . '[username]" value="' . esc_attr($settings['username']) . '" autocomplete="username"></td></tr>';
        echo '<tr><th>Новый пароль</th><td><input type="password" autocomplete="new-password" name="' . Settings::OPTION . '[password]"><p class="description">Оставьте пустым, чтобы сохранить текущий пароль.</p></td></tr>';
        echo '<tr><th>Заказов в пакете</th><td><input type="number" min="1" max="100" name="' . Settings::OPTION . '[batch_size]" value="' . (int) $settings['batch_size'] . '"></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';
        $this->import();
        echo '</div>';
    }

    private function diagnostics(): void
    {
        echo '<h2>Диагностика</h2><ul>';
        foreach ((new Diagnostics())->checks() as $label => $passed) {
            echo '<li><strong>' . ($passed ? '✓' : '✗') . '</strong> ' . esc_html($label) . '</li>';
        }
        echo '</ul><details><summary>Последние обмены</summary><table class="widefat"><thead><tr><th>UTC</th><th>Событие</th><th>Результат</th><th>Заказов</th></tr></thead><tbody>';
        foreach ((array) get_option('theobroma_1c_recent_log', []) as $entry) {
            echo '<tr><td>' . esc_html((string) ($entry['time'] ?? '')) . '</td><td>' . esc_html((string) ($entry['event'] ?? '')) . '</td><td>' . esc_html((string) ($entry['result'] ?? '')) . '</td><td>' . (int) ($entry['order_count'] ?? 0) . '</td></tr>';
        }
        echo '</tbody></table></details>';
    }

    private function import(): void
    {
        echo '<hr><h2>Импорт идентификаторов Ozon</h2>';
        echo '<p>Загрузите TSV/CSV из кабинета Ozon. Плагин не сопоставляет товары только по названию: неоднозначные строки нужно выбрать вручную.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="theobroma_1c_ozon_preview">';
        wp_nonce_field('theobroma_1c_ozon_preview');
        echo '<input type="file" name="ozon_file" accept=".csv,.tsv,text/csv,text/tab-separated-values" required> ';
        submit_button('Предварительный просмотр', 'secondary', 'submit', false);
        echo '</form>';

        $preview = OzonImportPage::previewData();
        if (!$preview) {
            return;
        }

        $products = wc_get_products(['status' => ['publish', 'private', 'draft'], 'limit' => -1]);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="theobroma_1c_ozon_apply">';
        wp_nonce_field('theobroma_1c_ozon_apply');
        echo '<table class="widefat"><thead><tr><th>Строка</th><th>Статус</th><th>Товар WooCommerce</th></tr></thead><tbody>';
        foreach ($preview as $index => $item) {
            $row = $item['row'];
            echo '<tr><td>' . esc_html($row->clientArticle . ' — ' . $row->name) . '</td><td>' . esc_html((string) $item['status']) . '</td><td>';
            if ((int) $item['product_id'] > 0) {
                echo '#' . (int) $item['product_id'];
            } else {
                echo '<select name="product_id[' . (int) $index . ']"><option value="">Не сопоставлять</option>';
                foreach ($products as $product) {
                    echo '<option value="' . (int) $product->get_id() . '">' . esc_html($product->get_name() . ' [' . $product->get_sku() . ']') . '</option>';
                }
                echo '</select>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Применить однозначные и выбранные сопоставления');
        echo '</form>';
    }
}

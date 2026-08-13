<?php
declare(strict_types=1);

namespace Theobroma\OneC\Admin;

use Theobroma\OneC\Settings\{ExchangeOptions, Settings};

final class SettingsPage
{
    private const SCREEN = 'woocommerce_page_theobroma-1c';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu(): void
    {
        add_submenu_page('woocommerce', 'Интеграция с 1С', 'Интеграция с 1С', 'manage_woocommerce', 'theobroma-1c', [$this, 'render']);
    }

    public function assets(string $hook): void
    {
        if ($hook !== self::SCREEN) {
            return;
        }
        wp_enqueue_style('theobroma-1c-admin', plugins_url('assets/admin.css', THEOBROMA_1C_FILE), [], '0.2.0');
        wp_enqueue_style('theobroma-1c-directions', plugins_url('assets/directions.css', THEOBROMA_1C_FILE), ['theobroma-1c-admin'], '0.3.0');
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
            ...ExchangeOptions::fromArray($input)->toArray(),
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = (new Settings())->get();
        $configured = $settings['username'] !== '' && $settings['password_hash'] !== '';
        $ready = $settings['enabled'] && $configured;
        $url = home_url('/theobroma-1c/exchange');

        echo '<div class="wrap theobroma-1c">';
        echo '<header class="theobroma-1c__hero">';
        echo '<div><p class="theobroma-1c__eyebrow">WooCommerce · CommerceML 2.05</p><h1>Интеграция с 1С</h1><p class="theobroma-1c__lead">Передача оплаченных заказов из интернет-магазина в 1С.</p></div>';
        echo '<span class="theobroma-1c-status ' . ($ready ? 'is-ready' : 'is-pending') . '"><span aria-hidden="true"></span>' . ($ready ? 'Обмен готов' : 'Требуется настройка') . '</span>';
        echo '</header>';
        echo '<section class="theobroma-1c-endpoint"><div><span>URL для разработчиков 1С</span><code>' . esc_html($url) . '</code></div><a class="button" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">Проверить URL</a></section>';

        echo '<div class="theobroma-1c__grid">';
        $this->connection($settings);
        $this->diagnostics();
        echo '</div>';
        $this->history();
        $this->import();
        echo '</div>';
    }

    /** @param array<string,mixed> $settings */
    private function connection(array $settings): void
    {
        echo '<section class="theobroma-1c-card"><div class="theobroma-1c-card__head"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><div><h2>Подключение к 1С</h2><p>Создайте отдельные учётные данные только для обмена.</p></div></div>';
        echo '<form method="post" action="options.php" class="theobroma-1c-form">';
        settings_fields('theobroma_1c');
        echo '<label class="theobroma-1c-switch"><input type="checkbox" name="' . Settings::OPTION . '[enabled]" value="1" ' . checked($settings['enabled'], true, false) . '><span aria-hidden="true"></span><strong>Включить обмен заказами</strong></label>';
        echo '<fieldset class="theobroma-1c-directions"><legend>Направления обмена</legend>';
        foreach (['export_orders'=>['Экспорт оплаченных заказов в 1С','Состав, доставка, оплата, скидки и возвраты.'],'import_order_statuses'=>['Импорт статусов заказов из 1С','Только для существующих заказов WC-ORDER-ID.'],'import_stock'=>['Импорт остатков из 1С','Только для однозначно сопоставленных товаров.'],'import_prices'=>['Импорт цен из 1С','Не изменяет SKU, название, описание и изображения.']] as $key=>[$title,$hint]) {
            echo '<label class="theobroma-1c-option"><input type="hidden" name="'.Settings::OPTION.'['.$key.']" value="0"><input type="checkbox" name="'.Settings::OPTION.'['.$key.']" value="1" '.checked(!empty($settings[$key]),true,false).'><span><strong>'.esc_html($title).'</strong><small>'.esc_html($hint).'</small></span></label>';
        }
        echo '</fieldset>';
        echo '<label><span>Логин</span><input type="text" name="' . Settings::OPTION . '[username]" value="' . esc_attr($settings['username']) . '" autocomplete="username" placeholder="Например, exchange_1c"></label>';
        echo '<label><span>Новый пароль</span><input type="password" autocomplete="new-password" name="' . Settings::OPTION . '[password]" placeholder="' . ($settings['password_hash'] !== '' ? 'Пароль уже установлен' : 'Задайте надёжный пароль') . '"><small>Оставьте пустым, чтобы не менять установленный пароль.</small></label>';
        echo '<label class="theobroma-1c-form__short"><span>Заказов в одном пакете</span><input type="number" min="1" max="100" name="' . Settings::OPTION . '[batch_size]" value="' . (int) $settings['batch_size'] . '"><small>Обычно достаточно 50.</small></label>';
        echo '<label class="theobroma-1c-form__short"><span>Максимальный XML-файл, МБ</span><input type="number" min="1" max="20" name="'.Settings::OPTION.'[upload_limit_mb]" value="'.(int)$settings['upload_limit_mb'].'"><small>Жёсткий предел — 20 МБ.</small></label>';
        submit_button('Сохранить настройки', 'primary', 'submit', false);
        echo '</form></section>';
    }

    private function diagnostics(): void
    {
        echo '<section class="theobroma-1c-card"><div class="theobroma-1c-card__head"><span class="dashicons dashicons-heart" aria-hidden="true"></span><div><h2>Готовность системы</h2><p>Проверки окружения и настроек подключения.</p></div></div><ul class="theobroma-1c-checks">';
        foreach ((new Diagnostics())->checks() as $label => $passed) {
            echo '<li class="' . ($passed ? 'is-passed' : 'is-failed') . '"><span class="dashicons ' . ($passed ? 'dashicons-yes-alt' : 'dashicons-warning') . '" aria-hidden="true"></span><span>' . esc_html($label) . '</span><strong>' . ($passed ? 'Готово' : 'Проверьте') . '</strong></li>';
        }
        echo '</ul><div class="theobroma-1c-note"><strong>Что передать разработчикам 1С</strong><span>URL обмена, логин, пароль и тип обмена <code>sale</code>.</span></div></section>';
    }

    private function history(): void
    {
        $entries = (array) get_option('theobroma_1c_recent_log', []);
        echo '<section class="theobroma-1c-card theobroma-1c-history"><div class="theobroma-1c-card__head"><span class="dashicons dashicons-backup" aria-hidden="true"></span><div><h2>Последние обмены</h2><p>Технический журнал без паролей и персональных данных покупателей.</p></div></div>';
        if ($entries === []) {
            echo '<div class="theobroma-1c-empty"><span class="dashicons dashicons-clock" aria-hidden="true"></span><strong>Обменов пока не было</strong><p>Записи появятся после первого обращения 1С к URL обмена.</p></div></section>';
            return;
        }
        echo '<div class="theobroma-1c-table-wrap"><table><thead><tr><th>Время (UTC)</th><th>Событие</th><th>Результат</th><th>Заказов</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            echo '<tr><td>' . esc_html((string) ($entry['time'] ?? '')) . '</td><td>' . esc_html((string) ($entry['event'] ?? '')) . '</td><td>' . esc_html((string) ($entry['result'] ?? '')) . '</td><td>' . (int) ($entry['order_count'] ?? 0) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function import(): void
    {
        echo '<details class="theobroma-1c-card theobroma-1c-ozon" ' . (isset($_GET['ozon_preview']) ? 'open' : '') . '><summary><span class="theobroma-1c-card__head"><span class="dashicons dashicons-products" aria-hidden="true"></span><span><strong>Сопоставление товаров из Ozon</strong><small>Необязательный инструмент для заполнения внешних идентификаторов.</small></span></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary><div class="theobroma-1c-ozon__body">';
        echo '<div class="theobroma-1c-ozon__explain"><p><strong>Что делает:</strong> записывает Ozon Product ID, Ozon SKU, EAN и артикул клиента в товары WooCommerce. Эти значения помогают 1С однозначно определить позиции заказа.</p><p><strong>Чего не делает:</strong> Не импортирует заказы Ozon, не меняет основной SKU WooCommerce и не подключается к API Ozon.</p></div>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="theobroma-1c-upload"><input type="hidden" name="action" value="theobroma_1c_ozon_preview">';
        wp_nonce_field('theobroma_1c_ozon_preview');
        echo '<label><span>Выгрузка из кабинета Ozon</span><input type="file" name="ozon_file" accept=".csv,.tsv,text/csv,text/tab-separated-values" required><small>TSV или CSV, до 5 МБ и 5000 строк.</small></label>';
        submit_button('Проверить файл', 'secondary', 'submit', false);
        echo '</form>';
        $this->preview();
        echo '</div></details>';
    }

    private function preview(): void
    {
        $preview = OzonImportPage::previewData();
        if (!$preview) {
            return;
        }
        $products = wc_get_products(['status' => ['publish', 'private', 'draft'], 'limit' => -1]);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="theobroma-1c-preview"><input type="hidden" name="action" value="theobroma_1c_ozon_apply">';
        wp_nonce_field('theobroma_1c_ozon_apply');
        echo '<h3>Предварительный просмотр</h3><div class="theobroma-1c-table-wrap"><table><thead><tr><th>Строка Ozon</th><th>Статус</th><th>Товар WooCommerce</th></tr></thead><tbody>';
        foreach ($preview as $index => $item) {
            $row = $item['row'];
            echo '<tr><td><strong>' . esc_html($row->clientArticle) . '</strong><br><small>' . esc_html($row->name) . '</small></td><td><span class="theobroma-1c-match">' . esc_html((string) $item['status']) . '</span></td><td>';
            if ((int) $item['product_id'] > 0) {
                echo '<strong>#' . (int) $item['product_id'] . '</strong>';
            } else {
                echo '<select name="product_id[' . (int) $index . ']"><option value="">Не сопоставлять</option>';
                foreach ($products as $product) {
                    echo '<option value="' . (int) $product->get_id() . '">' . esc_html($product->get_name() . ' [' . $product->get_sku() . ']') . '</option>';
                }
                echo '</select>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        submit_button('Применить сопоставления');
        echo '</form>';
    }
}

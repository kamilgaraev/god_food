<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Products\OzonCatalogAudit;

final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Интеграции Theobroma', 'theobroma-commerce'),
            __('Интеграции', 'theobroma-commerce'),
            'manage_woocommerce',
            'theobroma-commerce',
            [$this, 'render']
        );
    }

    public function settings(): void
    {
        register_setting('theobroma_commerce', 'theobroma_commerce_settings', [
            'type' => 'array',
            'sanitize_callback' => function (mixed $input): array {
                $existing = get_option('theobroma_commerce_settings', []);
                $existing = is_array($existing) ? $existing : [];
                $settings = new Settings();
                $next = $settings->sanitize(is_array($input) ? $input : [], $existing);
                if ($settings->ozonCredentialsChanged($existing, $next)) {
                    (new WordPressTokenStore())->forget();
                }
                return $next;
            },
            'default' => (new Settings())->defaults(),
            'show_in_rest' => false,
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $values = array_merge((new Settings())->defaults(), (array) get_option('theobroma_commerce_settings', []));
        $cdekNotice = get_transient(CdekConnectionAction::NOTICE_PREFIX . get_current_user_id());
        if (is_array($cdekNotice)) {
            delete_transient(CdekConnectionAction::NOTICE_PREFIX . get_current_user_id());
        }
        $mapsNotice = get_transient(YandexMapsConnectionAction::NOTICE_PREFIX . get_current_user_id());
        if (is_array($mapsNotice)) {
            delete_transient(YandexMapsConnectionAction::NOTICE_PREFIX . get_current_user_id());
        }
        $catalogAudit = (new OzonCatalogAudit())->audit(wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'return' => 'objects',
        ]));
        $notice = get_transient(OzonConnectionAction::NOTICE_PREFIX . get_current_user_id());
        if (is_array($notice)) {
            delete_transient(OzonConnectionAction::NOTICE_PREFIX . get_current_user_id());
        }
        $ozonTokens = (new WordPressTokenStore())->get();
        $ozonAuthorized = is_array($ozonTokens) && !empty($ozonTokens['refresh_token']);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Доставка Theobroma', 'theobroma-commerce'); ?></h1>
            <p><?php esc_html_e('Способы доставки не показываются покупателям, пока обязательная конфигурация не завершена.', 'theobroma-commerce'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('theobroma_commerce'); ?>
                <h2><?php esc_html_e('СДЭК API v2', 'theobroma-commerce'); ?></h2>
                <?php $this->checkbox('cdek_enabled', __('Включить после проверки подключения', 'theobroma-commerce'), $values); ?>
                <?php $this->text('cdek_client_id', __('Account / Client ID', 'theobroma-commerce'), $values); ?>
                <?php $this->secret('cdek_client_secret', __('Secure password / Client secret', 'theobroma-commerce'), $values, defined('THEOBROMA_CDEK_CLIENT_SECRET')); ?>
                <?php $this->number('cdek_sender_city_code', __('Код города отправителя СДЭК', 'theobroma-commerce'), $values); ?>
                <?php $this->text('cdek_sender_address', __('Адрес отправителя', 'theobroma-commerce'), $values); ?>
                <?php if (is_array($cdekNotice) && isset($cdekNotice['status'], $cdekNotice['message'])) : ?>
                    <div class="notice notice-<?php echo $cdekNotice['status'] === 'success' ? 'success' : 'error'; ?> inline"><p><?php echo esc_html((string) $cdekNotice['message']); ?></p></div>
                <?php endif; ?>
                <p><button type="submit" class="button" form="theobroma-cdek-connection-check"><?php esc_html_e('Проверить подключение СДЭК', 'theobroma-commerce'); ?></button></p>

                <h2><?php esc_html_e('Ozon Доставка (частное Seller API приложение)', 'theobroma-commerce'); ?></h2>
                <?php if (is_array($notice) && isset($notice['status'], $notice['message'])) : ?>
                    <div class="notice notice-<?php echo $notice['status'] === 'success' ? 'success' : 'error'; ?> inline"><p><?php echo esc_html((string) $notice['message']); ?></p></div>
                <?php endif; ?>
                <p><?php echo esc_html(sprintf(__('SKU Ozon заполнены: %1$d из %2$d товаров.', 'theobroma-commerce'), $catalogAudit['mapped'], $catalogAudit['total'])); ?></p>
                <?php if ($catalogAudit['missing_product_ids'] !== []) : ?>
                    <p class="notice notice-warning inline">
                        <?php echo esc_html(sprintf(__('Не заполнен Ozon SKU у товаров: %s', 'theobroma-commerce'), implode(', ', $catalogAudit['missing_product_ids']))); ?>
                    </p>
                <?php endif; ?>
                <?php $this->text('ozon_client_id', __('Client ID частного приложения', 'theobroma-commerce'), $values); ?>
                <?php $this->secret('ozon_client_secret', __('Secret частного приложения', 'theobroma-commerce'), $values, defined('THEOBROMA_OZON_CLIENT_SECRET')); ?>

                <h2><?php esc_html_e('Карты пунктов выдачи', 'theobroma-commerce'); ?></h2>
                <p><label for="theobroma-map-provider">Карта и поиск адресов</label><br>
                    <select id="theobroma-map-provider" name="theobroma_commerce_settings[map_provider]">
                        <option value="yandex" <?php selected($values['map_provider'] ?? 'yandex', 'yandex'); ?>>Яндекс Карты</option>
                        <option value="osm" <?php selected($values['map_provider'] ?? 'yandex', 'osm'); ?>>OpenStreetMap + Photon</option>
                    </select>
                </p>
                <p>OpenStreetMap + Photon работают без ключей Яндекса. Ключи сохраняются при переключении. Публичные сервисы OSM и Photon могут ограничивать нагрузку; список ПВЗ и ручной ввод остаются доступны.</p>
                <?php $this->text('yandex_maps_js_key', __('Ключ JavaScript API Яндекс Карт', 'theobroma-commerce'), $values); ?>
                <?php $this->secret('yandex_suggest_key', __('Ключ API Геосаджеста Яндекс', 'theobroma-commerce'), $values, defined('THEOBROMA_YANDEX_SUGGEST_KEY')); ?>
                <?php $this->secret('yandex_geocoder_key', __('Ключ HTTP Геокодера Яндекс', 'theobroma-commerce'), $values, defined('THEOBROMA_YANDEX_GEOCODER_KEY')); ?>
                <?php if (is_array($mapsNotice)) : ?>
                    <?php foreach (['javascript', 'suggest', 'geocoder'] as $mapService) : ?>
                        <?php if (isset($mapsNotice[$mapService]['status'], $mapsNotice[$mapService]['message'])) : ?>
                            <?php $mapStatus = (string) $mapsNotice[$mapService]['status']; ?>
                            <div class="notice notice-<?php echo $mapStatus === 'valid' ? 'success' : ($mapStatus === 'invalid' ? 'error' : 'warning'); ?> inline"><p><?php echo esc_html((string) $mapsNotice[$mapService]['message']); ?></p></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p><button type="submit" class="button" form="theobroma-yandex-maps-check"><?php esc_html_e('Проверить ключи карт', 'theobroma-commerce'); ?></button></p>
                <?php submit_button(); ?>
            </form>
            <form id="theobroma-yandex-maps-check" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none">
                <input type="hidden" name="action" value="<?php echo esc_attr(YandexMapsConnectionAction::ACTION); ?>">
                <?php wp_nonce_field(YandexMapsConnectionAction::ACTION); ?>
            </form>
            <form id="theobroma-cdek-connection-check" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none">
                <input type="hidden" name="action" value="<?php echo esc_attr(CdekConnectionAction::ACTION); ?>">
                <?php wp_nonce_field(CdekConnectionAction::ACTION); ?>
            </form>
            <p>
                <?php if ($ozonAuthorized) : ?>
                    <strong style="color:#008a20"><?php esc_html_e('Авторизация продавца Ozon получена.', 'theobroma-commerce'); ?></strong>
                <?php else : ?>
                    <strong><?php esc_html_e('Требуется авторизация продавца в Ozon.', 'theobroma-commerce'); ?></strong>
                <?php endif; ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                <input type="hidden" name="action" value="<?php echo esc_attr(OzonAuthorizationAction::ACTION); ?>">
                <?php wp_nonce_field(OzonAuthorizationAction::ACTION); ?>
                <?php submit_button(
                    $ozonAuthorized ? __('Повторно авторизовать в Ozon', 'theobroma-commerce') : __('Авторизовать в Ozon', 'theobroma-commerce'),
                    'primary',
                    'submit',
                    false
                ); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(OzonConnectionAction::ACTION); ?>">
                <?php wp_nonce_field(OzonConnectionAction::ACTION); ?>
                <?php submit_button(__('Проверить подключение Ozon', 'theobroma-commerce'), 'secondary', 'submit', false); ?>
            </form>
            <h2><?php esc_html_e('Callback URLs', 'theobroma-commerce'); ?></h2>
            <p>
                <strong>CDEK:</strong><br>
                <code><?php echo esc_html(rest_url('theobroma-commerce/v1/cdek/webhook')); ?></code>
            </p>
            <p>
                <strong>Ozon redirect URL:</strong><br>
                <code><?php echo esc_html(\Theobroma\Commerce\Rest\OzonOAuthCallbackController::redirectUri()); ?></code>
            </p>
        </div>
        <?php
    }

    /** @param array<string,mixed> $values */
    private function checkbox(string $key, string $label, array $values): void
    {
        printf('<p><label><input type="checkbox" name="theobroma_commerce_settings[%1$s]" value="yes" %2$s> %3$s</label></p>', esc_attr($key), checked($values[$key] ?? 'no', 'yes', false), esc_html($label));
    }

    /** @param array<string,mixed> $values */
    private function text(string $key, string $label, array $values): void
    {
        printf('<p><label>%1$s<br><input class="regular-text" type="text" name="theobroma_commerce_settings[%2$s]" value="%3$s"></label></p>', esc_html($label), esc_attr($key), esc_attr((string) ($values[$key] ?? '')));
    }

    /** @param array<string,mixed> $values */
    private function number(string $key, string $label, array $values): void
    {
        printf('<p><label>%1$s<br><input type="number" min="0" name="theobroma_commerce_settings[%2$s]" value="%3$d"></label></p>', esc_html($label), esc_attr($key), (int) ($values[$key] ?? 0));
    }

    /** @param array<string,mixed> $values */
    private function secret(string $key, string $label, array $values, bool $constantConfigured): void
    {
        $hint = $constantConfigured ? __('Задан константой в wp-config.php', 'theobroma-commerce') : (($values[$key] ?? '') !== '' ? __('Сохранён; оставьте поле пустым, чтобы не менять', 'theobroma-commerce') : __('Не задан', 'theobroma-commerce'));
        printf('<p><label>%1$s<br><input class="regular-text" type="password" autocomplete="new-password" name="theobroma_commerce_settings[%2$s]" value="" placeholder="%3$s"></label></p>', esc_html($label), esc_attr($key), esc_attr($hint));
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Ozon\OzonReadinessFactory;
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
                return (new Settings())->sanitize(is_array($input) ? $input : [], is_array($existing) ? $existing : []);
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
        $catalogAudit = (new OzonCatalogAudit())->audit(wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'return' => 'objects',
        ]));
        $ozon = (new OzonReadinessFactory())->build(
            $values,
            $values['ozon_access_token'] !== '' || defined('THEOBROMA_OZON_ACCESS_TOKEN'),
            wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'objects'])
        );
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

                <h2><?php esc_html_e('Ozon Доставка (частное Seller API приложение)', 'theobroma-commerce'); ?></h2>
                <p><strong><?php esc_html_e('Статус готовности:', 'theobroma-commerce'); ?></strong> <?php echo esc_html($ozon->status()); ?></p>
                <p><?php echo esc_html(sprintf(__('SKU Ozon заполнены: %1$d из %2$d товаров.', 'theobroma-commerce'), $catalogAudit['mapped'], $catalogAudit['total'])); ?></p>
                <?php if ($catalogAudit['missing_product_ids'] !== []) : ?>
                    <p class="notice notice-warning inline">
                        <?php echo esc_html(sprintf(__('Не заполнен Ozon SKU у товаров: %s', 'theobroma-commerce'), implode(', ', $catalogAudit['missing_product_ids']))); ?>
                    </p>
                <?php endif; ?>
                <?php $this->checkbox('ozon_enabled', __('Включить после полного живого теста', 'theobroma-commerce'), $values); ?>
                <?php $this->checkbox('ozon_approved', __('Заявка Ozon Доставки одобрена', 'theobroma-commerce'), $values); ?>
                <?php $this->secret('ozon_access_token', __('OAuth access token частного приложения', 'theobroma-commerce'), $values, defined('THEOBROMA_OZON_ACCESS_TOKEN')); ?>
                <?php $this->checkbox('ozon_products_mapped', __('Остатки для сопоставленных SKU зарегистрированы в Ozon', 'theobroma-commerce'), $values); ?>
                <?php $this->checkbox('ozon_live_test_completed', __('Реальный полный цикл заказа проверен', 'theobroma-commerce'), $values); ?>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Callback URLs', 'theobroma-commerce'); ?></h2>
            <code><?php echo esc_html(rest_url('theobroma-commerce/v1/cdek/webhook')); ?></code>
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

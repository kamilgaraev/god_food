<?php

declare(strict_types=1);

namespace Theobroma\Analytics;

final class SettingsPage
{
    public const OPTION = 'theobroma_analytics_settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void
    {
        add_options_page('Аналитика Theobroma', 'Аналитика Theobroma', 'manage_options', 'theobroma-analytics', [$this, 'render']);
    }

    public function settings(): void
    {
        register_setting('theobroma_analytics', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [new AnalyticsConfig(), 'sanitize'],
            'default' => (new AnalyticsConfig())->defaults(),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $config = array_replace((new AnalyticsConfig())->defaults(), (array) get_option(self::OPTION, []));
        ?>
        <div class="wrap">
            <h1>Аналитика Theobroma</h1>
            <p>Счётчик загружается только после согласия посетителя на cookie. До ввода валидного ID запросы в Яндекс.Метрику не выполняются.</p>
            <form method="post" action="options.php">
                <?php settings_fields('theobroma_analytics'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="theobroma-counter-id">ID счётчика</label></th><td><input id="theobroma-counter-id" name="<?php echo esc_attr(self::OPTION); ?>[counter_id]" value="<?php echo esc_attr((string) $config['counter_id']); ?>" inputmode="numeric" pattern="[1-9][0-9]{0,14}" class="regular-text"></td></tr>
                    <?php foreach (['clickmap' => 'Карта кликов', 'track_links' => 'Отслеживание ссылок', 'accurate_bounce' => 'Точный показатель отказов', 'webvisor' => 'Вебвизор'] as $key => $label) : ?>
                        <tr><th scope="row"><?php echo esc_html($label); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION . '[' . $key . ']'); ?>" value="1" <?php checked(!empty($config[$key])); ?>> Включить</label></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

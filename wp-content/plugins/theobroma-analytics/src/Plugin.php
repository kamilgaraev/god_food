<?php

declare(strict_types=1);

namespace Theobroma\Analytics;

final class Plugin
{
    public static function boot(): void
    {
        (new SettingsPage())->register();
        add_action('wp_head', [self::class, 'render'], 20);
    }

    public static function render(): void
    {
        $config = array_replace((new AnalyticsConfig())->defaults(), (array) get_option(SettingsPage::OPTION, []));
        $javascript = (new MetrikaRenderer())->javascript($config);
        if ($javascript === '') {
            return;
        }
        wp_print_inline_script_tag($javascript, ['id' => 'theobroma-yandex-metrika']);
    }
}

<?php
/**
 * Plugin Name: Theobroma Analytics
 * Description: Управляемое подключение Яндекс.Метрики с сохранением отказа посетителя от аналитики.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Theobroma
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

foreach (['AnalyticsConfig.php', 'MetrikaRenderer.php', 'SettingsPage.php', 'Plugin.php'] as $file) {
    require_once __DIR__ . '/src/' . $file;
}

add_action('plugins_loaded', [Theobroma\Analytics\Plugin::class, 'boot']);

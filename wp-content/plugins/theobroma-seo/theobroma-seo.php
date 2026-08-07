<?php
/**
 * Plugin Name: Theobroma SEO
 * Description: Управляемые метаданные, Open Graph и структурированные данные для сайта Theobroma.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Theobroma
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

foreach ([
    'SeoDocument.php',
    'MetadataRenderer.php',
    'SchemaFactory.php',
    'SiteVerificationRenderer.php',
    'SiteVerificationSettings.php',
    'WordPressDocumentResolver.php',
    'SeoMetaBox.php',
    'Plugin.php',
] as $file) {
    require_once __DIR__ . '/src/' . $file;
}

add_action('plugins_loaded', [Theobroma\Seo\Plugin::class, 'boot']);

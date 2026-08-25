<?php
/**
 * Plugin Name: Theobroma Contact Forms Loader
 * Description: Автоматически загружает настройки публичных форм заявок.
 * Version: 1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$theobroma_contact_forms_plugin = WP_PLUGIN_DIR . '/theobroma-contact-forms/theobroma-contact-forms.php';
if (is_readable($theobroma_contact_forms_plugin)) {
    require_once $theobroma_contact_forms_plugin;
}

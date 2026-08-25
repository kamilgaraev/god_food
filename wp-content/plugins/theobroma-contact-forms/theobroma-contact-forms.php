<?php
/**
 * Plugin Name: Theobroma Contact Forms
 * Description: Настройки полей и получателей форм заявок Theobroma.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Text Domain: theobroma-contact-forms
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Submission.php';
require_once __DIR__ . '/src/FieldRenderer.php';
require_once __DIR__ . '/src/SettingsPage.php';
require_once __DIR__ . '/src/Plugin.php';

\Theobroma\ContactForms\Plugin::boot();

function theobroma_contact_forms_definition(string $formId): array
{
    return \Theobroma\ContactForms\Plugin::instance()->definition($formId);
}

function theobroma_contact_forms_render_fields(string $formId): string
{
    return \Theobroma\ContactForms\Plugin::instance()->renderFields($formId);
}

/** @param array<string, string> $values */
function theobroma_contact_forms_validate(string $formId, array $values): bool
{
    return \Theobroma\ContactForms\Plugin::instance()->validate($formId, $values);
}

function theobroma_contact_forms_recipient(string $formId): string
{
    return \Theobroma\ContactForms\Plugin::instance()->recipient($formId);
}

/** @param array<string, string> $values @return list<string> */
function theobroma_contact_forms_notification_lines(string $formId, array $values): array
{
    return \Theobroma\ContactForms\Plugin::instance()->notificationLines($formId, $values);
}

/** @param array<string, string> $values @return array<string, string> */
function theobroma_contact_forms_values(string $formId, array $values): array
{
    return \Theobroma\ContactForms\Plugin::instance()->values($formId, $values);
}

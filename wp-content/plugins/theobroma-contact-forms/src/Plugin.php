<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class Plugin
{
    private static ?self $instance = null;

    public function __construct(
        private readonly Settings $settings = new Settings(),
        private readonly FieldRenderer $renderer = new FieldRenderer(),
        private readonly Submission $submission = new Submission()
    ) {
    }

    public static function boot(): self
    {
        $plugin = self::$instance ??= new self();
        (new SettingsPage($plugin->settings))->register();
        return $plugin;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** @return array<string, mixed> */
    public function definition(string $formId): array
    {
        $fallbackEmail = sanitize_email((string) get_option('admin_email', ''));
        $stored = get_option(Settings::OPTION, array());
        $settings = $this->settings->sanitize(is_array($stored) ? $stored : array(), $fallbackEmail);

        return $settings[$formId] ?? $this->settings->defaults($fallbackEmail)['home'];
    }

    public function renderFields(string $formId): string
    {
        return $this->renderer->render($this->definition($formId));
    }

    /** @param array<string, string> $values */
    public function validate(string $formId, array $values): bool
    {
        return $this->submission->isValid($values, $this->definition($formId));
    }

    public function recipient(string $formId): string
    {
        return (string) ($this->definition($formId)['recipient'] ?? '');
    }

    /** @param array<string, string> $values @return list<string> */
    public function notificationLines(string $formId, array $values): array
    {
        return $this->submission->lines($values, $this->definition($formId));
    }

    /** @param array<string, string> $values @return array<string, string> */
    public function values(string $formId, array $values): array
    {
        return $this->submission->values($values, $this->definition($formId));
    }
}

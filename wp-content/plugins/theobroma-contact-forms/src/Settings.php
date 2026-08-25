<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class Settings
{
    public const OPTION = 'theobroma_contact_forms_settings';
    public const MAX_CUSTOM_FIELDS = 30;

    /** @return list<string> */
    public function formIds(): array
    {
        return array('home', 'cooperation');
    }

    /** @return list<string> */
    public function fieldIds(): array
    {
        return array('name', 'phone', 'email', 'message');
    }

    /** @return array<string, array<string, mixed>> */
    public function defaults(string $fallbackEmail): array
    {
        $form = array(
            'recipient' => $this->validEmail($fallbackEmail) ? $fallbackEmail : '',
            'fields' => array(
                'name' => array('enabled' => true, 'required' => false),
                'phone' => array('enabled' => true, 'required' => true),
                'email' => array('enabled' => false, 'required' => false),
                'message' => array('enabled' => true, 'required' => false),
            ),
            'custom_fields' => array(),
        );

        return array(
            'home' => $form,
            'cooperation' => $form,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, array<string, mixed>>
     */
    public function sanitize(array $input, string $fallbackEmail): array
    {
        $defaults = $this->defaults($fallbackEmail);
        $result = array();

        foreach ($this->formIds() as $formId) {
            $submitted = isset($input[$formId]) && is_array($input[$formId]) ? $input[$formId] : null;
            if ($submitted === null) {
                $result[$formId] = $defaults[$formId];
                continue;
            }

            $recipient = trim((string) ($submitted['recipient'] ?? ''));
            $result[$formId] = array(
                'recipient' => $this->validEmail($recipient) ? $recipient : $defaults[$formId]['recipient'],
                'fields' => array(),
                'custom_fields' => array(),
            );
            $submittedFields = isset($submitted['fields']) && is_array($submitted['fields'])
                ? $submitted['fields']
                : array();

            foreach ($this->fieldIds() as $fieldId) {
                $field = isset($submittedFields[$fieldId]) && is_array($submittedFields[$fieldId])
                    ? $submittedFields[$fieldId]
                    : array();
                $required = $this->flag($field['required'] ?? false);
                $result[$formId]['fields'][$fieldId] = array(
                    'enabled' => $required || $this->flag($field['enabled'] ?? false),
                    'required' => $required,
                );
            }

            $customFields = isset($submitted['custom_fields']) && is_array($submitted['custom_fields'])
                ? array_slice($submitted['custom_fields'], 0, self::MAX_CUSTOM_FIELDS)
                : array();
            $usedKeys = array();
            foreach ($customFields as $index => $customField) {
                if (!is_array($customField)) {
                    continue;
                }
                $label = $this->text($customField['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $baseKey = $this->key($customField['key'] ?? '');
                if ($baseKey === '') {
                    $baseKey = 'field-' . ((int) $index + 1);
                }
                $key = $baseKey;
                $suffix = 2;
                while (isset($usedKeys[$key])) {
                    $key = $baseKey . '-' . $suffix;
                    $suffix++;
                }
                $usedKeys[$key] = true;

                $type = (string) ($customField['type'] ?? 'text');
                if (!in_array($type, array('text', 'email', 'tel', 'number', 'textarea', 'select'), true)) {
                    $type = 'text';
                }
                $result[$formId]['custom_fields'][] = array(
                    'key' => $key,
                    'label' => $label,
                    'type' => $type,
                    'placeholder' => $this->text($customField['placeholder'] ?? ''),
                    'required' => $this->flag($customField['required'] ?? false),
                    'options' => $type === 'select' ? $this->options($customField['options'] ?? array()) : array(),
                );
            }
        }

        return $result;
    }

    private function flag(mixed $value): bool
    {
        return in_array($value, array(true, 1, '1', 'yes', 'on', 'true'), true);
    }

    private function validEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function text(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function key(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = (string) preg_replace('/[^a-z0-9_-]+/', '-', $key);
        return trim($key, '-_');
    }

    /** @return list<string> */
    private function options(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/\R/u', (string) $value);
        $options = array();
        foreach (is_array($items) ? $items : array() as $item) {
            $option = $this->text($item);
            if ($option !== '' && !in_array($option, $options, true)) {
                $options[] = $option;
            }
        }
        return $options;
    }
}

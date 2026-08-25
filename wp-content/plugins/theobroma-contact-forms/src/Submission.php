<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class Submission
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $definition
     */
    public function isValid(array $values, array $definition): bool
    {
        $hasValue = false;
        foreach ($this->enabledFields($definition) as $fieldId => $field) {
            $value = $this->clean($values[$fieldId] ?? '', $fieldId === 'message');
            $hasValue = $hasValue || $value !== '';
            if (!empty($field['required']) && $value === '') {
                return false;
            }
            if ($fieldId === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
        }

        foreach ($this->customFields($definition) as $field) {
            $value = $this->customValue($values, $field);
            $hasValue = $hasValue || $value !== '';
            if (!empty($field['required']) && $value === '') {
                return false;
            }
            if (($field['type'] ?? '') === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
            if (($field['type'] ?? '') === 'number' && $value !== '' && !is_numeric($value)) {
                return false;
            }
            if (($field['type'] ?? '') === 'select' && $value !== '' && !in_array($value, $field['options'] ?? array(), true)) {
                return false;
            }
        }

        return $hasValue;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    public function lines(array $values, array $definition): array
    {
        $labels = array(
            'name' => 'Имя',
            'phone' => 'Телефон',
            'email' => 'E-mail',
            'message' => 'Комментарий',
        );
        $lines = array();

        foreach ($this->enabledFields($definition) as $fieldId => $field) {
            $value = $this->clean($values[$fieldId] ?? '', $fieldId === 'message');
            if ($value !== '' && isset($labels[$fieldId])) {
                $lines[] = $labels[$fieldId] . ': ' . $value;
            }
        }

        foreach ($this->customFields($definition) as $field) {
            $value = $this->customValue($values, $field);
            if ($value !== '') {
                $lines[] = (string) $field['label'] . ': ' . $value;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $definition
     * @return array<string, string>
     */
    public function values(array $values, array $definition): array
    {
        $enabled = array();
        foreach ($this->enabledFields($definition) as $fieldId => $field) {
            $enabled[$fieldId] = $this->clean($values[$fieldId] ?? '', $fieldId === 'message');
        }
        foreach ($this->customFields($definition) as $field) {
            $enabled['custom_' . $field['key']] = $this->customValue($values, $field);
        }

        return $enabled;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<string, mixed>>
     */
    private function enabledFields(array $definition): array
    {
        $fields = isset($definition['fields']) && is_array($definition['fields'])
            ? $definition['fields']
            : array();

        return array_filter($fields, static fn(mixed $field): bool => is_array($field) && !empty($field['enabled']));
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<array<string, mixed>>
     */
    private function customFields(array $definition): array
    {
        $fields = isset($definition['custom_fields']) && is_array($definition['custom_fields'])
            ? $definition['custom_fields']
            : array();

        return array_values(array_filter($fields, static fn(mixed $field): bool =>
            is_array($field) && isset($field['key'], $field['label'], $field['type'])
        ));
    }

    /** @param array<string, mixed> $values @param array<string, mixed> $field */
    private function customValue(array $values, array $field): string
    {
        $custom = isset($values['custom']) && is_array($values['custom']) ? $values['custom'] : array();
        return $this->clean($custom[(string) $field['key']] ?? '', ($field['type'] ?? '') === 'textarea');
    }

    private function clean(mixed $value, bool $multiline = false): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }
        $value = (string) $value;
        if (function_exists('wp_unslash')) {
            $value = wp_unslash($value);
        }
        if ($multiline && function_exists('sanitize_textarea_field')) {
            return trim(sanitize_textarea_field($value));
        }
        if (function_exists('sanitize_text_field')) {
            return trim(sanitize_text_field($value));
        }
        $value = strip_tags($value);
        return trim($multiline ? $value : (string) preg_replace('/\s+/u', ' ', $value));
    }
}

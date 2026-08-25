<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class Submission
{
    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $definition
     */
    public function isValid(array $values, array $definition): bool
    {
        $hasValue = false;
        foreach ($this->enabledFields($definition) as $fieldId => $field) {
            $value = trim((string) ($values[$fieldId] ?? ''));
            $hasValue = $hasValue || $value !== '';
            if (!empty($field['required']) && $value === '') {
                return false;
            }
            if ($fieldId === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
        }

        return $hasValue;
    }

    /**
     * @param array<string, string> $values
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
            $value = trim((string) ($values[$fieldId] ?? ''));
            if ($value !== '' && isset($labels[$fieldId])) {
                $lines[] = $labels[$fieldId] . ': ' . $value;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $definition
     * @return array<string, string>
     */
    public function values(array $values, array $definition): array
    {
        $enabled = array();
        foreach ($this->enabledFields($definition) as $fieldId => $field) {
            $enabled[$fieldId] = trim((string) ($values[$fieldId] ?? ''));
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
}

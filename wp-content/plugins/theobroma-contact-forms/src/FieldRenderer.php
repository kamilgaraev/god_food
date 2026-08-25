<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class FieldRenderer
{
    /** @param array<string, mixed> $definition */
    public function render(array $definition): string
    {
        $fields = isset($definition['fields']) && is_array($definition['fields'])
            ? $definition['fields']
            : array();
        $html = '';

        foreach (array('name', 'phone', 'email', 'message') as $fieldId) {
            $field = isset($fields[$fieldId]) && is_array($fields[$fieldId]) ? $fields[$fieldId] : array();
            if (empty($field['enabled'])) {
                continue;
            }
            $required = !empty($field['required']) ? ' required' : '';
            if ($fieldId === 'name') {
                $html .= '<input type="text" name="name" placeholder="Имя" autocomplete="name" aria-label="Имя"' . $required . '>';
            } elseif ($fieldId === 'phone') {
                $html .= '<div class="phone-field"><input type="tel" name="phone" value="+7" placeholder="+7 (000) 000-00-00" inputmode="tel" autocomplete="tel" maxlength="18" aria-label="Телефон"' . $required . '></div>';
            } elseif ($fieldId === 'email') {
                $html .= '<input type="email" name="email" placeholder="E-mail" inputmode="email" autocomplete="email" aria-label="E-mail"' . $required . '>';
            } else {
                $html .= '<input class="message-field" type="text" name="message" placeholder="Ваш вопрос или комментарий" aria-label="Ваш вопрос"' . $required . '>';
            }
        }

        $customFields = isset($definition['custom_fields']) && is_array($definition['custom_fields'])
            ? $definition['custom_fields']
            : array();
        foreach ($customFields as $field) {
            if (!is_array($field) || empty($field['key']) || empty($field['label']) || empty($field['type'])) {
                continue;
            }
            $key = $this->escape((string) $field['key']);
            $label = $this->escape((string) $field['label']);
            $placeholder = $this->escape((string) ($field['placeholder'] ?? $field['label']));
            $name = 'custom[' . $key . ']';
            $required = !empty($field['required']) ? ' required' : '';
            $type = (string) $field['type'];

            if ($type === 'textarea') {
                $html .= '<textarea class="field-wide" name="' . $name . '" placeholder="' . $placeholder . '" aria-label="' . $label . '" data-field-width="full"' . $required . '></textarea>';
                continue;
            }
            if ($type === 'select') {
                $html .= '<select name="' . $name . '" aria-label="' . $label . '"' . $required . '>';
                $html .= '<option value="">' . ($placeholder !== '' ? $placeholder : $label) . '</option>';
                foreach (is_array($field['options'] ?? null) ? $field['options'] : array() as $option) {
                    $option = $this->escape((string) $option);
                    $html .= '<option value="' . $option . '">' . $option . '</option>';
                }
                $html .= '</select>';
                continue;
            }

            $allowedType = in_array($type, array('text', 'email', 'tel', 'number'), true) ? $type : 'text';
            $attributes = $allowedType === 'number' ? ' inputmode="decimal" step="any"' : '';
            $html .= '<input type="' . $allowedType . '" name="' . $name . '" placeholder="' . $placeholder . '" aria-label="' . $label . '"' . $attributes . $required . '>';
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

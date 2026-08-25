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

        return $html;
    }
}

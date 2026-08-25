<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class Settings
{
    public const OPTION = 'theobroma_contact_forms_settings';

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
}

<?php

declare(strict_types=1);

/** @param array<string, mixed> $request */
function theobroma_contact_request_is_valid(array $request, int $now): bool {
    return trim((string) ($request['name'] ?? '')) !== ''
        && trim((string) ($request['phone'] ?? '')) !== ''
        && theobroma_contact_request_security_is_valid($request, $now);
}

/** @param array<string, mixed> $request */
function theobroma_contact_request_security_is_valid(array $request, int $now): bool {
    $started_at = (int) ($request['started_at'] ?? 0);

    return (string) ($request['consent'] ?? '') === '1'
        && trim((string) ($request['honeypot'] ?? '')) === ''
        && $started_at > 0
        && ($now - $started_at) >= 3;
}

/** @param array<string, mixed> $request */
function theobroma_standard_contact_request_is_valid(array $request, string $formId, int $now): bool {
    if (!theobroma_contact_request_security_is_valid($request, $now)) {
        return false;
    }
    if (function_exists('theobroma_contact_forms_validate')) {
        return theobroma_contact_forms_validate($formId, $request);
    }

    return trim((string) ($request['name'] ?? '')) !== ''
        && trim((string) ($request['phone'] ?? '')) !== '';
}

function theobroma_standard_contact_request_recipient(string $formId, string $fallback): string {
    $recipient = function_exists('theobroma_contact_forms_recipient')
        ? theobroma_contact_forms_recipient($formId)
        : $fallback;

    return filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false ? $recipient : $fallback;
}

/** @param array<string, mixed> $request @return list<string> */
function theobroma_standard_contact_request_lines(string $formId, array $request): array {
    if (function_exists('theobroma_contact_forms_notification_lines')) {
        return theobroma_contact_forms_notification_lines($formId, $request);
    }

    return theobroma_contact_request_lines($request);
}

/** @param array<string, mixed> $request @return array<string, string> */
function theobroma_standard_contact_request_values(string $formId, array $request): array {
    if (function_exists('theobroma_contact_forms_values')) {
        return theobroma_contact_forms_values($formId, $request);
    }

    return array_map('strval', array_intersect_key($request, array_flip(array('name', 'phone', 'email', 'message'))));
}

/**
 * @param array<string, mixed> $request
 * @return list<string>
 */
function theobroma_contact_request_lines(array $request): array {
    $labels = array(
        'name' => 'Имя',
        'phone' => 'Телефон',
        'email' => 'E-mail',
        'company' => 'Компания',
        'gift_type' => 'Тип подарка',
        'volume' => 'Тираж',
        'branding' => 'Брендирование',
        'message' => 'Комментарий',
    );
    $lines = array();

    foreach ($labels as $key => $label) {
        $value = trim((string) ($request[$key] ?? ''));
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    return $lines;
}

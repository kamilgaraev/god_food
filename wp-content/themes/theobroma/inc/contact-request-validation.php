<?php

declare(strict_types=1);

/** @param array<string, mixed> $request */
function theobroma_contact_request_is_valid(array $request, int $now): bool {
    $started_at = (int) ($request['started_at'] ?? 0);

    return trim((string) ($request['name'] ?? '')) !== ''
        && trim((string) ($request['phone'] ?? '')) !== ''
        && (string) ($request['consent'] ?? '') === '1'
        && trim((string) ($request['honeypot'] ?? '')) === ''
        && $started_at > 0
        && ($now - $started_at) >= 3;
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

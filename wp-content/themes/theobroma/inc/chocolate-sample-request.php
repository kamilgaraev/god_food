<?php

declare(strict_types=1);

/** @param array<string, mixed> $request */
function theobroma_chocolate_sample_request_is_valid(array $request, int $now): bool {
    $inn = trim((string) ($request['inn'] ?? ''));
    $lengths = array(
        'company' => 160,
        'venue_type' => 80,
        'city' => 120,
        'name' => 120,
        'phone' => 40,
        'email' => 254,
        'message' => 2000,
    );
    foreach ($lengths as $key => $maximum) {
        if (mb_strlen((string) ($request[$key] ?? ''), 'UTF-8') > $maximum) {
            return false;
        }
    }

    return trim((string) ($request['company'] ?? '')) !== ''
        && preg_match('/^(?:\d{10}|\d{12})$/D', $inn) === 1
        && trim((string) ($request['name'] ?? '')) !== ''
        && trim((string) ($request['phone'] ?? '')) !== ''
        && theobroma_contact_request_security_is_valid($request, $now);
}

/** @param array<string, mixed> $request */
function theobroma_chocolate_sample_rate_limit_key(array $request, string $clientAddress): string {
    $phone = preg_replace('/\D+/', '', (string) ($request['phone'] ?? '')) ?? '';
    $identity = implode('|', array(
        trim($clientAddress),
        trim((string) ($request['inn'] ?? '')),
        $phone,
    ));

    return 'theobroma_sample_d_' . substr(hash_hmac('sha256', $identity, wp_salt('nonce')), 0, 32);
}

function theobroma_chocolate_sample_acquire_limit(string $optionName, int $now, int $ttl): bool {
    $expires_at = (int) get_option($optionName, 0);
    if ($expires_at > $now) {
        return false;
    }
    if ($expires_at > 0) {
        delete_option($optionName);
    }

    return add_option($optionName, $now + $ttl, '', false);
}

/** @param array<string, mixed> $request @return list<string>|WP_Error */
function theobroma_chocolate_sample_reserve_limits(array $request, string $clientAddress, int $now): array|WP_Error {
    $dedupe_key = theobroma_chocolate_sample_rate_limit_key($request, $clientAddress);
    if (!theobroma_chocolate_sample_acquire_limit($dedupe_key, $now, 300)) {
        return new WP_Error('theobroma_sample_duplicate', 'Повторная заявка уже принята.');
    }

    $address = trim($clientAddress) !== '' ? trim($clientAddress) : 'unknown';
    $ip_hash = substr(hash_hmac('sha256', $address, wp_salt('nonce')), 0, 32);
    for ($slot = 0; $slot < 3; $slot++) {
        $ip_key = 'theobroma_sample_ip_' . $ip_hash . '_' . $slot;
        if (theobroma_chocolate_sample_acquire_limit($ip_key, $now, 300)) {
            return array($dedupe_key, $ip_key);
        }
    }

    delete_option($dedupe_key);
    return new WP_Error('theobroma_sample_rate_limited', 'Слишком много заявок. Попробуйте позже.');
}

/** @param list<string> $optionNames */
function theobroma_release_chocolate_sample_limits(array $optionNames): void {
    foreach ($optionNames as $optionName) {
        delete_option($optionName);
    }
}

/** @param array<string, mixed> $request @return list<string> */
function theobroma_chocolate_sample_request_lines(array $request): array {
    $labels = array(
        'company' => 'Компания',
        'inn' => 'ИНН',
        'venue_type' => 'Тип заведения',
        'city' => 'Город',
        'name' => 'Имя',
        'phone' => 'Телефон',
        'email' => 'E-mail',
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

/** @param array<string, mixed> $post @return array<string, string|int> */
function theobroma_chocolate_sample_request_from_post(array $post): array {
    return array(
        'company' => sanitize_text_field(wp_unslash($post['company'] ?? '')),
        'inn' => sanitize_text_field(wp_unslash($post['inn'] ?? '')),
        'venue_type' => sanitize_text_field(wp_unslash($post['venue_type'] ?? '')),
        'city' => sanitize_text_field(wp_unslash($post['city'] ?? '')),
        'name' => sanitize_text_field(wp_unslash($post['name'] ?? '')),
        'phone' => sanitize_text_field(wp_unslash($post['phone'] ?? '')),
        'email' => sanitize_email(wp_unslash($post['email'] ?? '')),
        'message' => sanitize_textarea_field(wp_unslash($post['message'] ?? '')),
        'consent' => sanitize_text_field(wp_unslash($post['consent'] ?? '')),
        'honeypot' => sanitize_text_field(wp_unslash($post['theobroma_website'] ?? '')),
        'started_at' => absint($post['theobroma_form_started'] ?? 0),
    );
}

/** @param array<string, mixed> $request @return int|WP_Error */
function theobroma_save_chocolate_sample_request(array $request, string $clientAddress = ''): mixed {
    $reservation = theobroma_chocolate_sample_reserve_limits($request, $clientAddress, time());
    if (is_wp_error($reservation)) {
        return $reservation;
    }

    $lines = theobroma_chocolate_sample_request_lines($request);
    $title_parts = array_values(array_filter(array(
        trim((string) ($request['company'] ?? '')),
        trim((string) ($request['inn'] ?? '')),
        trim((string) ($request['name'] ?? '')),
        trim((string) ($request['phone'] ?? '')),
    )));
    $request_id = wp_insert_post(array(
        'post_type' => 'contact_request',
        'post_status' => 'publish',
        'post_title' => implode(' — ', $title_parts),
        'post_content' => implode("\n", $lines),
    ), true);

    if (is_wp_error($request_id)) {
        theobroma_release_chocolate_sample_limits($reservation);
        return $request_id;
    }

    wp_schedule_single_event(time() + 300, 'theobroma_release_sample_rate_limit', array($reservation));

    update_post_meta((int) $request_id, '_theobroma_request_type', 'chocolate_samples');
    update_post_meta((int) $request_id, '_theobroma_request_company', (string) $request['company']);
    update_post_meta((int) $request_id, '_theobroma_request_inn', (string) $request['inn']);
    if (($request['email'] ?? '') !== '') {
        update_post_meta((int) $request_id, '_theobroma_request_email', (string) $request['email']);
    }

    $mail_sent = wp_mail(
        sanitize_email((string) get_option('admin_email')),
        'Запрос пробников шоколада — ' . (string) $request['company'],
        implode("\n", $lines)
    );
    update_post_meta((int) $request_id, '_theobroma_request_mail_sent', $mail_sent ? '1' : '0');

    return (int) $request_id;
}

add_action('theobroma_release_sample_rate_limit', 'theobroma_release_chocolate_sample_limits', 10, 1);

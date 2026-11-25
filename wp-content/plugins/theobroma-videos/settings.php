<?php
/** Normalize both collections before saving to the WordPress options table. */
function theobroma_videos_defaults(): array {
    $section = ['enabled' => false, 'title' => '', 'description' => '', 'items' => []];
    return [
        'stories' => array_replace($section, ['title' => 'Ближе к Theobroma']),
        'reels' => array_replace($section, ['title' => 'Шоколад в движении']),
    ];
}

function theobroma_videos_sanitize($input): array {
    $result = theobroma_videos_defaults();
    $input = is_array($input) ? $input : [];
    foreach ($result as $key => $defaults) {
        $section = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : [];
        $result[$key]['enabled'] = !empty($section['enabled']);
        $result[$key]['title'] = sanitize_text_field($section['title'] ?? $defaults['title']);
        $result[$key]['description'] = sanitize_textarea_field($section['description'] ?? '');
        $items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
        foreach (array_slice($items, 0, 30) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[$key]['items'][] = [
                'enabled' => !empty($item['enabled']),
                'title' => sanitize_text_field($item['title'] ?? ''),
                'description' => sanitize_textarea_field($item['description'] ?? ''),
                'video' => esc_url_raw((string) ($item['video'] ?? ''), ['http', 'https']),
                'poster_id' => absint($item['poster_id'] ?? 0),
                'button_enabled' => !empty($item['button_enabled']),
                'button_text' => sanitize_text_field($item['button_text'] ?? ''),
                'button_url' => esc_url_raw((string) ($item['button_url'] ?? ''), ['http', 'https']),
            ];
        }
    }
    return $result;
}

function theobroma_videos_settings(): array {
    return theobroma_videos_sanitize(get_option('theobroma_videos', theobroma_videos_defaults()));
}

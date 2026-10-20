<?php
declare(strict_types=1);

/** Content shared by the corporate page and its settings screen. */
function theobroma_corporate_sections_defaults(): array {
    return array(
        'portfolio' => array(
            'enabled' => true,
            'title' => 'Подарки с вашим характером',
            'title_accent' => 'характером',
            'intro' => 'Несколько примеров подарков Theobroma. Подберём состав и оформление под вашу компанию, повод и бюджет.',
            'items' => array(
                array('title' => 'Знак внимания', 'text' => 'Шоколад в крафт-конверте с лентой и персональной открыткой.', 'asset' => 'discovery-original.png', 'attachment_id' => 0, 'alt' => 'Набор Theobroma в крафт-конверте'),
                array('title' => 'В фирменных цветах', 'text' => 'Подарочная коробка, любимые вкусы и детали, которые рассказывают о вашем бренде.', 'asset' => 'for-her-original.png', 'attachment_id' => 0, 'alt' => 'Подарочная коробка шоколада Theobroma'),
                array('title' => 'Личное послание', 'text' => 'Открытка с вашим текстом превращает подарок в тёплое обращение к получателю.', 'asset' => 'personalization-original.png', 'attachment_id' => 0, 'alt' => 'Персональная открытка на подарке Theobroma'),
                array('title' => 'Особый повод', 'text' => 'Премиальный набор в коробке с тиснением логотипа. Состав собираем под ваш запрос.', 'asset' => 'premium-original.png', 'attachment_id' => 0, 'alt' => 'Премиальный подарочный набор Theobroma'),
            ),
        ),
        'process' => array(
            'enabled' => true,
            'title' => 'От идеи до готового подарка',
            'title_accent' => 'готового подарка',
            'intro' => 'Берём на себя шоколад, оформление и сборку. С вами согласуем главное — состав, макет и дату.',
            'factory_title' => 'Собственное производство',
            'factory_value' => '8',
            'factory_unit' => 'тонн шоколада в месяц',
            'factory_text' => 'Своё оборудование и собственная лаборатория — состав, вкус и сроки контролируем сами.',
            'timing_title' => 'Что влияет на срок',
            'timing_text' => 'Наклейки и шуберы — от 5 дней. Коробки и пакеты с печатью — от 10 дней. Финальные сроки фиксируем в договоре после утверждения макета.',
            'items' => array(
                array('title' => 'Знакомимся с задачей', 'text' => 'Обсуждаем повод, тираж, бюджет и дату. Уточняем, кому вы хотите подарить шоколад.'),
                array('title' => 'Подбираем подарок', 'text' => 'Предлагаем вкусы, состав и формат упаковки. Находим сочетание, которое подходит вашей компании.'),
                array('title' => 'Согласуем оформление', 'text' => 'Готовим макет с вашим логотипом и пожеланиями. Утверждаем детали перед запуском тиража.'),
                array('title' => 'Договор и оплата', 'text' => 'Готовим договор поставки, счёт и закрывающие документы. Возможна оплата 50/50.'),
                array('title' => 'Производим шоколад', 'text' => 'Делаем шоколад и печатаем упаковку параллельно. Обычно больше времени занимает именно упаковка.'),
                array('title' => 'Собираем и отправляем', 'text' => 'Собираем комплекты вручную и упаковываем для перевозки. Согласуем доставку в офис или самовывоз.'),
            ),
        ),
    );
}

/** Normalize untrusted option data; an explicitly empty list stays empty. */
function theobroma_sanitize_corporate_sections(mixed $value): array {
    $defaults = theobroma_corporate_sections_defaults();
    $input = is_array($value) ? $value : array();
    $result = array();
    $text = static function (mixed $value, bool $multiline = false): string {
        if (!is_scalar($value)) {
            return '';
        }
        return $multiline ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
    };
    $assets = array_column($defaults['portfolio']['items'], 'asset');

    foreach ($defaults as $section => $fallback) {
        $candidate = isset($input[$section]) && is_array($input[$section]) ? $input[$section] : array();
        $result[$section] = array();
        foreach ($fallback as $key => $default) {
            if ($key === 'items') {
                continue;
            }
            $raw = array_key_exists($key, $candidate) ? $candidate[$key] : $default;
            $result[$section][$key] = $key === 'enabled'
                ? in_array($raw, array(true, 1, '1', 'on'), true)
                : $text($raw, in_array($key, array('intro', 'factory_text', 'timing_text'), true));
            if ($key === 'title' && $result[$section][$key] === '') {
                $result[$section][$key] = $fallback['title'];
            }
        }
        $rows = array_key_exists('items', $candidate) ? $candidate['items'] : $fallback['items'];
        $result[$section]['items'] = array();
        foreach (is_array($rows) ? $rows : array() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = array('title' => $text($row['title'] ?? ''), 'text' => $text($row['text'] ?? '', true));
            if ($item['title'] === '') {
                continue;
            }
            if ($section === 'portfolio') {
                $id = isset($row['attachment_id']) && is_scalar($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
                $item['attachment_id'] = $id > 0 && wp_attachment_is_image($id) ? $id : 0;
                $item['asset'] = isset($row['asset']) && in_array($row['asset'], $assets, true) ? $row['asset'] : '';
                $item['alt'] = $text($row['alt'] ?? '');
            }
            $result[$section]['items'][] = $item;
            if (count($result[$section]['items']) >= 12) {
                break;
            }
        }
    }
    return $result;
}

function theobroma_corporate_sections(): array {
    return theobroma_sanitize_corporate_sections(get_option('theobroma_corporate_sections', array()));
}

/** Highlight plain text using the corporate page's existing heading style. */
function theobroma_corporate_section_heading(array $section): string {
    $title = $section['title'];
    $accent = $section['title_accent'];
    $position = $accent !== '' ? strpos($title, $accent) : false;
    if ($position === false) {
        return esc_html($title);
    }
    return esc_html(substr($title, 0, $position)) . '<em>' . esc_html($accent) . '</em>'
        . esc_html(substr($title, $position + strlen($accent)));
}

/** Same image source on the storefront and in the editor. */
function theobroma_corporate_portfolio_image(array $item, bool $admin = false): string {
    if ($item['attachment_id']) {
        return wp_get_attachment_image($item['attachment_id'], $admin ? 'medium' : 'large', false, array(
            'alt' => $item['alt'] !== '' ? $item['alt'] : $item['title'],
            'loading' => 'lazy',
            'decoding' => 'async',
            'sizes' => '(max-width:600px) calc(100vw - 4rem), (max-width:900px) calc(50vw - 2.375rem), (max-width:1216px) calc(25vw - 1.8125rem), 275px',
        ));
    }
    if ($item['asset'] === '') {
        return '';
    }
    $url = get_template_directory_uri() . '/assets/images/corporate/' . $item['asset'];
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($item['alt'] !== '' ? $item['alt'] : $item['title']) . '" loading="lazy" decoding="async">';
}

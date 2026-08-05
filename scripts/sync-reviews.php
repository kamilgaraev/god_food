<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$reviews = array(
    array('slug' => 'review-yulia', 'name' => 'Юлия', 'date' => '2025-05-12 12:00:00', 'text' => 'Настоящий шоколад отличного состава и&nbsp;качества с&nbsp;чудесным ароматом и&nbsp;волшебно-мягким вкусом.'),
    array('slug' => 'review-lyubov', 'name' => 'Любовь', 'date' => '2025-06-23 12:00:00', 'text' => 'Отменный шоколад! Вот уж&nbsp;точно Пища Богов! Очень вкусный&nbsp;— рекомендую. В&nbsp;меру сладкий, в&nbsp;меру горький, приятный, маслянистый.'),
    array('slug' => 'review-arina', 'name' => 'Арина', 'date' => '2025-09-06 12:00:00', 'text' => 'Шоколад находка. Сладость присутствует. Во&nbsp;рту крошится, как&nbsp;бы все обволакивая, что мне нравится. Очень удивил шоколад, однозначно буду заказывать ещё.'),
    array('slug' => 'review-natalya', 'name' => 'Наталья', 'date' => '2025-02-18 12:00:00', 'text' => 'Отличный, шоколадный, насыщенный вкус какао. Детям делаю с&nbsp;сахаром и&nbsp;молоком, получается очень вкусно.'),
    array('slug' => 'review-olga', 'name' => 'Ольга', 'date' => '2025-05-12 12:00:00', 'text' => 'Вкус отменный, до&nbsp;это брала другие порошки алкализованные вкусные, но&nbsp;этот натуральный и&nbsp;тоже очень вкусный поэтому предпочтение этому.'),
    array('slug' => 'review-marina', 'name' => 'Марина', 'date' => '2025-09-19 12:00:00', 'text' => 'Состав натуральный, срок годности отличный.<br>Упакован хорошо.<br>Люблю горький шоколад, спасибо!'),
    array('slug' => 'review-kristina', 'name' => 'Кристина', 'date' => '2025-05-03 12:00:00', 'text' => 'Один раз попробовав, сложно в&nbsp;дальнейшем воспринимать другой шоколад. Нежный за&nbsp;счет пористой структуры, в&nbsp;меру горький, самое главное&nbsp;— натуральный!<br>Большое спасибо производителю! Надеюсь со&nbsp;временем качество не&nbsp;ухудшится.'),
);

foreach ($reviews as $order => $review) {
    $existing = get_page_by_path($review['slug'], OBJECT, 'theobroma_review');
    $data = array(
        'post_type' => 'theobroma_review',
        'post_status' => 'publish',
        'post_name' => $review['slug'],
        'post_title' => $review['name'],
        'post_content' => $review['text'],
        'post_date' => $review['date'],
        'post_date_gmt' => get_gmt_from_date($review['date']),
        'menu_order' => $order,
    );
    if ($existing instanceof WP_Post) {
        $data['ID'] = $existing->ID;
    }
    $post_id = wp_insert_post($data, true);
    if (is_wp_error($post_id)) {
        throw new RuntimeException($post_id->get_error_message());
    }
    echo $review['slug'] . ':' . $post_id . PHP_EOL;
}

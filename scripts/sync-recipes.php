<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$recipes = array(
    'classic' => array(
        'title' => 'Рецепт классического какао',
        'accent' => 'Рецепт',
        'heading' => 'классического какао',
        'excerpt' => 'Простой и вкусный рецепт какао. Лучший напиток для вашего завтрака.',
        'image' => 'recipe-classic-detail.jpg',
        'layout' => 'classic',
        'card_title' => "Какао\nклассический",
        'cooking_time' => '5 минут',
        'ingredients' => array(
            array('name' => 'Какао-порошок', 'amount' => '1 чайная ложка'),
            array('name' => 'Сахар (тростниковый или кокосовый)', 'amount' => '1 чайная ложка'),
            array('name' => 'Вода', 'amount' => '20 мл'),
            array('name' => 'Молоко', 'amount' => '280 мл'),
        ),
        'steps' => array(
            array('text' => 'Насыпать в чашку 1 чайную ложку какао, добавить сахар по вкусу'),
            array('text' => 'Добавить воду и тщательно перемешать'),
            array('text' => 'Добавить в смесь тёплое молоко, тщательно перемешать и наслаждаться полезным напитком'),
        ),
    ),
    'marshmallow' => array(
        'title' => 'Рецепт какао с маршмеллоу',
        'accent' => 'Рецепт',
        'heading' => 'какао с маршмеллоу',
        'excerpt' => 'Простой и вкусный рецепт какао. Лучший напиток для вашего завтрака.',
        'image' => 'recipe-marshmallow-detail.jpg',
        'layout' => 'long',
        'card_title' => "Какао с\nмаршмеллоу",
        'cooking_time' => '5 минут',
        'ingredients' => array(
            array('name' => 'Какао-порошок Theobroma', 'amount' => '1.5 чайной ложки'),
            array('name' => 'Сахар (тростниковый или кокосовый)', 'amount' => '1 чайная ложка'),
            array('name' => 'Вода', 'amount' => '90 мл'),
            array('name' => 'Молоко', 'amount' => '180 мл'),
            array('name' => 'Маршмеллоу', 'amount' => '15 г (горсть)'),
            array('name' => 'Натуральный шоколад', 'amount' => 'Несколько кусочков'),
        ),
        'steps' => array(
            array('text' => 'Молоко и воду соедините в ковше или кастрюльке. Поставьте на плиту, доведите до кипения'),
            array('text' => 'Пока молоко закипает, смешайте какао-порошок и сахар в другой ёмкости'),
            array('text' => 'Снимите молоко с нагрева, влейте в сухую смесь какао и сахара немного закипевшего молока и перемешайте, чтобы не было комочков'),
            array('text' => 'Перелейте разведённое какао обратно в молоко, перемешайте, повторно доведите молоко до кипения и на медленном огне проварите какао с молоком 1 минуту'),
            array('text' => 'Налейте какао в кружку, добавьте маршмеллоу, присыпьте какао с маршмеллоу тёртым шоколадом'),
            array('text' => 'Вкуснейший напиток готов, можете наслаждаться какао с воздушной «шапочкой» из маршмеллоу! Приятного какао!'),
        ),
    ),
    'banana' => array(
        'title' => 'Рецепт бананового смузи с какао',
        'accent' => 'Рецепт',
        'heading' => 'бананового смузи с какао',
        'excerpt' => 'Простой и вкусный рецепт какао. Лучший напиток для вашего завтрака.',
        'image' => 'recipe-banana-detail.jpg',
        'layout' => 'banana',
        'card_title' => "Рецепт бананового\nсмузи с какао",
        'cooking_time' => '5 минут',
        'ingredients' => array(
            array('name' => 'Молоко', 'amount' => '150 мл'),
            array('name' => 'Банан', 'amount' => '1 шт'),
            array('name' => 'Какао-порошок', 'amount' => '1 столовая ложка'),
            array('name' => 'Корица молотая', 'amount' => '1 чайная ложка'),
            array('name' => 'Натуральный шоколад', 'amount' => 'Несколько кусочков'),
        ),
        'steps' => array(
            array('text' => 'Банан очистите от кожуры и нарежьте мякоть кружочками. Чем более спелым и мягким будет банан — тем слаще будет смузи и тем более однородной и нежной будет текстура напитка'),
            array('text' => 'В чаше блендера соедините банан, какао-порошок и корицу. Измельчите на высокой скорости до получения гладкого и однородного пюре'),
            array('text' => 'Влейте нагретое молоко. Снова пробейте на высокой скорости блендера примерно 1–2 минуты, до однородности.'),
            array('text' => 'Попробуйте на вкус, если сладости банана недостаточно, то можно добавить немного мёда или сахара'),
            array('text' => 'Шоколад натрите на мелкой тёрке. Перелейте банановый смузи с какао в стакан, присыпьте шоколадом и подавайте к столу. Приятного аппетита!'),
        ),
    ),
);

foreach ($recipes as $slug => $data) {
    $existing = get_page_by_path($slug, OBJECT, 'theobroma_recipe');
    $post = array(
        'post_type' => 'theobroma_recipe',
        'post_status' => 'publish',
        'post_title' => $data['title'],
        'post_name' => $slug,
        'post_excerpt' => $data['excerpt'],
        'menu_order' => array_search($slug, array_keys($recipes), true),
    );
    if ($existing instanceof WP_Post) {
        $post['ID'] = $existing->ID;
        $post_id = wp_update_post($post, true);
    } else {
        $post_id = wp_insert_post($post, true);
    }
    if (is_wp_error($post_id)) {
        fwrite(STDERR, $slug . ': ' . $post_id->get_error_message() . PHP_EOL);
        exit(1);
    }
    foreach (array('accent', 'heading', 'image', 'layout', 'card_title', 'cooking_time') as $field) {
        update_post_meta($post_id, '_theobroma_' . $field, $data[$field]);
    }
    update_post_meta($post_id, '_theobroma_ingredients', wp_json_encode($data['ingredients'], JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_theobroma_steps', wp_json_encode($data['steps'], JSON_UNESCAPED_UNICODE));
    echo $slug . ':' . $post_id . PHP_EOL;
}

flush_rewrite_rules(false);

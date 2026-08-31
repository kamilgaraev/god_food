<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function fetch_source_page_content(string $url): string {
    $response = wp_remote_get($url, array('timeout' => 20));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return '';
    }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML((string) wp_remote_retrieve_body($response), LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' t-text ') and contains(concat(' ', normalize-space(@class), ' '), ' t-text_md ')]");
    if (!$nodes instanceof DOMNodeList) {
        return '';
    }
    $selected = null;
    $selected_length = 0;
    foreach ($nodes as $node) {
        $length = mb_strlen(trim((string) $node->textContent));
        if ($length > $selected_length) {
            $selected = $node;
            $selected_length = $length;
        }
    }
    if (!$selected instanceof DOMElement || $selected_length < 100) {
        return '';
    }
    $html = '';
    foreach ($selected->childNodes as $child) {
        $html .= (string) $document->saveHTML($child);
    }
    return wp_kses_post($html);
}

/** @param array{title:string,slug:string,content?:string,source?:string} $data */
function sync_page(array $data): int {
    $page = get_page_by_path($data['slug'], OBJECT, 'page');
    $content = $data['content'] ?? ($page instanceof WP_Post ? $page->post_content : '');
    if (isset($data['source'])) {
        $source_content = fetch_source_page_content($data['source']);
        if ($source_content !== '') {
            $content = $source_content;
        }
    }
    $post = array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $data['title'],
        'post_name' => $data['slug'],
        'post_content' => $content,
    );
    if ($page instanceof WP_Post) {
        $post['ID'] = $page->ID;
    }
    $page_id = wp_insert_post($post, true);
    if (is_wp_error($page_id)) {
        throw new RuntimeException($page_id->get_error_message());
    }
    return (int) $page_id;
}

$pages = array(
    array('title' => 'Политика конфиденциальности', 'slug' => 'policy', 'source' => 'https://theobroma.one/policy'),
    array('title' => 'Согласие на обработку персональных данных', 'slug' => 'consent'),
    array('title' => 'Пользовательское соглашение', 'slug' => 'agreement', 'source' => 'https://theobroma.one/agreement'),
    array('title' => 'Публичная оферта', 'slug' => 'oferta', 'source' => 'https://theobroma.one/oferta'),
    array('title' => 'Медиа', 'slug' => 'media'),
    array('title' => 'Корпоративные подарки', 'slug' => 'corporate-gifts'),
    array('title' => 'Пробники шоколада', 'slug' => 'chocolate-samples'),
);

foreach ($pages as $data) {
    try {
        echo $data['slug'] . ':' . sync_page($data) . PHP_EOL;
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $data['title'] . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

$privacy_page = get_page_by_path('policy', OBJECT, 'page');
$terms_page = get_page_by_path('agreement', OBJECT, 'page');
if ($privacy_page instanceof WP_Post) {
    update_option('wp_page_for_privacy_policy', $privacy_page->ID);
}
if ($terms_page instanceof WP_Post) {
    update_option('woocommerce_terms_page_id', $terms_page->ID);
}

$category = term_exists('media', 'category');
if (!$category) {
    $category = wp_insert_term('Медиа', 'category', array('slug' => 'media'));
}
if (is_wp_error($category)) {
    throw new RuntimeException($category->get_error_message());
}
$category_id = (int) (is_array($category) ? $category['term_id'] : $category);

$media_posts = array(
    array(
        'slug' => 'kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka',
        'title' => 'Как выбрать настоящий шоколад для ребенка?',
        'excerpt' => 'Рекомендации для мам о том, как выбирать сладкий десерт для детей',
        'date' => '2026-06-22 12:00:00',
        'source' => 'https://theobroma.one/tpost/lh452c15l1-kak-vibrat-nastoyaschii-shokolad-dlya-re',
        'image' => 'https://static.tildacdn.com/tild6263-6463-4539-b839-353663336239/photo.jpg',
        'content' => '"Все дети на свете обожают молочный шоколад. Все родители знают об этом. Но, к сожалению, не все взрослые знают, что должно быть внутри настоящего шоколада, который будет полезен".<br>Дмитрий Гаранин, основатель компании Theobroma «Пища Богов» рассказывает, как выбирать полезный шоколад для детей..',
        'article_link' => 'https://libertymag.ru/kak-vybrat-poleznyy-shokolad-dlya-rebyonka-na-chto-smotret-v-sostave',
        'product_skus' => array('theobroma-200-cow', 'theobroma-200-goat', 'theobroma-100-cow'),
    ),
    array(
        'slug' => 'pochemu-zozhniki-polyubili-shokolad-bez-sakhara',
        'title' => 'Почему ЗОЖ-ники полюбили шоколад без сахара',
        'excerpt' => 'Разбираемся, что стоит за трендом добавлять шоколад в свой рацион',
        'date' => '2026-06-16 12:00:00',
        'source' => 'https://theobroma.one/tpost/oksizysnx1-pochemu-zozh-niki-polyubili-shokolad-bez',
        'image' => 'https://static.tildacdn.com/tild3764-3561-4633-b737-373536383238/photo.jpg',
        'content' => '<strong>Дмитрий Гаранин</strong>, сооснователь и генеральный директор бренда натурального шоколада Theobroma «Пища Богов» рассказывает, почему горький шоколад стал частью культуры осознанного питания.',
        'article_link' => 'https://www.kiz.ru/content/fitnes-i-pitanie/pravilnoe-pitanie/polza-dlya-serdtsa-i-dolgoletiya-pravda-li-chto-gorkiy-shokolad-bez-sakhara-stanovitsya-lekarstvom/?sphrase_id=1570071',
        'product_skus' => array('theobroma-200-80', 'theobroma-200-70', 'theobroma-200-68-coriander'),
    ),
    array(
        'slug' => 'chto-oznachayut-protsenty-na-plitke-shokolada',
        'title' => 'Что означают проценты на плитке шоколада и как они влияют на вкус',
        'excerpt' => 'В этой статье вы узнаете, о чем может рассказать процент содержания какао на плитке',
        'date' => '2026-05-28 12:00:00',
        'source' => 'https://theobroma.one/tpost/16ok4dygz1-chto-oznachayut-protsenti-na-plitke-shok',
        'image' => 'https://static.tildacdn.com/tild3765-6637-4938-b838-323832666637/_3.jpg',
        'content' => 'Какао-масса формирует основной вкус, аромат и цвет шоколада. Об этом порталу rambler.ru рассказал основатель и генеральный директор компании Theobroma «Пища Богов» Дмитрий Гаранин.<br>«Иногда производители добавляют какао-порошок для корректировки структуры и содержания жира. Его доля в составе обычно очень маленькая, но тем не менее учитывается в проценте на упаковке. Чем выше процент какао, тем меньше в шоколаде доля сахара и тем более насыщенным становится вкус», — заявил эксперт.',
        'article_link' => 'https://www.rambler.ru/dom/kuhnya/56505434-chto-oznachayut-protsenty-na-plitke-shokolada-i-kak-oni-vliyayut-na-vkus/',
        'product_skus' => array('theobroma-200-68-coriander', 'theobroma-200-70', 'theobroma-200-80'),
    ),
    array(
        'slug' => 'kak-otlichit-nastoyashchiy-shokolad-ot-poddelki',
        'title' => 'Россиянам рассказали, как отличить настоящий шоколад от подделки',
        'excerpt' => 'Директор «Пищи Богов» Дмитрий Гаранин: в качественном шоколаде будет масло какао',
        'date' => '2026-05-14 12:00:00',
        'source' => 'https://theobroma.one/tpost/rx94zga5s1-rossiyanam-rasskazali-kak-otlichit-nasto',
        'image' => 'https://static.tildacdn.com/tild3636-6462-4562-b635-333437376330/shutterstock_2442900.jpg',
        'content' => 'В составе настоящего шоколада будет масло какао. Об этом «Газете.Ru» рассказал основатель и генеральный директор компании Theobroma «Пища Богов» Дмитрий Гаранин.<br>«В качественном шоколаде должно присутствовать масло какао. Это главный источник жира в шоколаде. Производители, которые заботятся о своей репутации и работают на современном оборудовании (обеспечивающем непрерывный и чистый производственный процесс), используют именно натуральное масло какао», — заявил эксперт.',
        'article_link' => 'https://www.gazeta.ru/style/news/2026/03/24/28121623.shtml',
        'product_skus' => array('theobroma-200-cow', 'theobroma-200-70', 'theobroma-cacao-200'),
    ),
);

foreach ($media_posts as $order => $data) {
    $existing = get_page_by_path($data['slug'], OBJECT, 'post');
    $post_data = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => $data['title'],
        'post_name' => $data['slug'],
        'post_excerpt' => $data['excerpt'],
        'post_content' => $data['content'],
        'post_date' => $data['date'],
        'post_category' => array($category_id),
        'menu_order' => $order,
    );
    if ($existing instanceof WP_Post) {
        $post_data['ID'] = $existing->ID;
    }
    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        throw new RuntimeException($post_id->get_error_message());
    }
    update_post_meta((int) $post_id, '_theobroma_source_url', esc_url_raw($data['source']));
    update_post_meta((int) $post_id, '_theobroma_article_link', esc_url_raw($data['article_link']));
    $product_ids = array();
    foreach ($data['product_skus'] as $product_sku) {
        $product_id = wc_get_product_id_by_sku($product_sku);
        if (!$product_id || !wc_get_product($product_id)) {
            throw new RuntimeException('Не найден WooCommerce-товар с SKU ' . $product_sku);
        }
        $product_ids[] = (int) $product_id;
    }
    update_post_meta((int) $post_id, '_theobroma_product_ids', $product_ids);
    $stored_image_source = (string) get_post_meta((int) $post_id, '_theobroma_media_image_source', true);
    if (!has_post_thumbnail((int) $post_id) || $stored_image_source !== $data['image']) {
        $attachment_id = media_sideload_image($data['image'], (int) $post_id, $data['title'], 'id');
        if (is_wp_error($attachment_id)) {
            fwrite(STDERR, $data['slug'] . ': ' . $attachment_id->get_error_message() . PHP_EOL);
        } else {
            set_post_thumbnail((int) $post_id, (int) $attachment_id);
            update_post_meta((int) $post_id, '_theobroma_media_image_source', esc_url_raw($data['image']));
        }
    }
    $thumbnail_id = get_post_thumbnail_id((int) $post_id);
    if ($thumbnail_id) {
        $metadata = wp_get_attachment_metadata($thumbnail_id);
        if (!is_array($metadata) || empty($metadata['sizes']['theobroma-media-card'])) {
            $generated = wp_generate_attachment_metadata($thumbnail_id, get_attached_file($thumbnail_id));
            if (is_array($generated)) {
                wp_update_attachment_metadata($thumbnail_id, $generated);
            }
        }
    }
    echo 'media-' . $data['slug'] . ':' . $post_id . PHP_EOL;
}

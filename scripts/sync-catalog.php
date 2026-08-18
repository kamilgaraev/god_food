<?php
declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once '/var/www/html/wp-content/themes/theobroma/inc/product-images.php';

if (!class_exists('WC_Product_Simple')) {
    fwrite(STDERR, "WooCommerce is not available.\n");
    exit(1);
}

function ensure_product_category(string $name, string $slug): int {
    $category = term_exists($slug, 'product_cat');
    if (!$category) {
        $category = wp_insert_term($name, 'product_cat', array('slug' => $slug));
    }
    if (is_wp_error($category)) {
        throw new RuntimeException($category->get_error_message());
    }
    return (int) (is_array($category) ? $category['term_id'] : $category);
}

$category_ids = array(
    'chocolate-200g' => ensure_product_category('Шоколад 200г', 'chocolate-200g'),
    'chocolate-100g' => ensure_product_category('Шоколад 100г', 'chocolate-100g'),
    'chocolate-30g' => ensure_product_category('Шоколад 30г', 'chocolate-30g'),
    'cacao' => ensure_product_category('Какао-порошок', 'cacao'),
    'chia' => ensure_product_category('Семена чиа', 'chia'),
);

$products = array(
    array('sku' => 'theobroma-200-68-coriander', 'name' => '68% горький шоколад 200г', 'description' => 'На кокосовом сахаре с кориандром', 'price' => '1426', 'image' => 'https://optim.tildacdn.com/stor6433-6632-4433-a439-363632396661/-/cover/312x390/center/center/-/format/webp/01b928381129561f2b0ec499f692ea63.jpg.webp'),
    array('sku' => 'theobroma-200-65-cinnamon', 'name' => '65% горький шоколад 200г', 'description' => 'На кокосовом сахаре с корицей', 'price' => '1400', 'image' => 'https://optim.tildacdn.com/stor3830-6631-4436-a565-303138666538/-/cover/312x390/center/center/-/format/webp/0aaabe7a1452ee246c00b48e35bfb127.jpg.webp'),
    array('sku' => 'theobroma-200-70', 'name' => '70% горький шоколад 200г', 'description' => 'На кокосовом сахаре', 'price' => '1418', 'image' => 'https://optim.tildacdn.com/stor6264-3664-4531-b132-636639353166/-/cover/312x390/center/center/-/format/webp/e3c7285ac0c64b6de6049970e97fcd3b.jpg.webp'),
    array('sku' => 'theobroma-200-80', 'name' => '80% горький шоколад 200г', 'description' => 'На тростниковом сахаре', 'price' => '1508', 'image' => 'https://optim.tildacdn.com/stor6339-6630-4430-b766-396135346636/-/cover/312x390/center/center/-/format/webp/f0b415ef770e39c21ceb4edb2533f1c3.jpg.webp'),
    array('sku' => 'theobroma-200-goat', 'name' => 'Молочный шоколад 200г', 'description' => 'На козьем молоке с финиковой пудрой', 'price' => '1464', 'image' => 'https://optim.tildacdn.com/stor6665-3132-4137-b134-393662623030/-/cover/312x390/center/center/-/format/webp/a109bf7c2f84b965763f053a4f5f823c.jpg.webp'),
    array('sku' => 'theobroma-200-cow', 'name' => 'Молочный шоколад 200г', 'description' => 'На коровьем молоке с финиковой пудрой', 'price' => '1230', 'image' => 'https://optim.tildacdn.com/stor6132-6638-4539-b434-313936633865/-/cover/312x390/center/center/-/format/webp/66253c82af3be4739bc88b8fce1a22e1.jpg.webp'),
    array('sku' => 'theobroma-100-68-coriander', 'name' => '68% горький шоколад 100г', 'description' => 'На кокосовом сахаре с кориандром', 'price' => '772', 'image' => 'https://optim.tildacdn.com/stor3663-6631-4065-a537-386531353639/-/cover/312x390/center/center/-/format/webp/dc8b431020fc509f009f290b1f81537f.jpg.webp', 'category' => 'chocolate-100g', 'order' => 0),
    array('sku' => 'theobroma-100-65-cinnamon', 'name' => '65% горький шоколад 100г', 'description' => 'На кокосовом сахаре с корицей', 'price' => '758', 'image' => 'https://optim.tildacdn.com/stor3466-6663-4434-a336-303432626237/-/cover/312x390/center/center/-/format/webp/9529885e1d1ad953aab14e0612d59631.jpg.webp', 'category' => 'chocolate-100g', 'order' => 1),
    array('sku' => 'theobroma-100-70', 'name' => '70% горький шоколад 100г', 'description' => 'На кокосовом сахаре', 'price' => '768', 'image' => 'https://optim.tildacdn.com/stor3963-6332-4464-b961-633165623531/-/cover/312x390/center/center/-/format/webp/6c7ebc56c8be76374c673631f09bc0de.jpg.webp', 'category' => 'chocolate-100g', 'order' => 2),
    array('sku' => 'theobroma-100-80', 'name' => '80% горький шоколад 100г', 'description' => 'На тростниковом сахаре', 'price' => '814', 'image' => 'https://optim.tildacdn.com/stor3733-3734-4531-b334-623732326561/-/cover/312x390/center/center/-/format/webp/0b11b5d593b042d9a5854dd945c4250e.jpg.webp', 'category' => 'chocolate-100g', 'order' => 3),
    array('sku' => 'theobroma-100-goat', 'name' => 'Молочный шоколад 100г', 'description' => 'На козьем молоке с финиковой пудрой', 'price' => '790', 'image' => 'https://optim.tildacdn.com/stor3736-3065-4233-b034-323266626430/-/cover/312x390/center/center/-/format/webp/0ff50b2de66a3c67ced38aff819ebf46.jpg.webp', 'category' => 'chocolate-100g', 'order' => 4),
    array('sku' => 'theobroma-100-cow', 'name' => 'Молочный шоколад 100г', 'description' => 'На коровьем молоке с финиковой пудрой', 'price' => '674', 'image' => 'https://optim.tildacdn.com/stor3534-6333-4134-a333-333766366634/-/cover/312x390/center/center/-/format/webp/c9439912d65f5e6a0885565b1246d707.jpg.webp', 'category' => 'chocolate-100g', 'order' => 5),
    array('sku' => 'theobroma-30-59-cherry-buckwheat', 'name' => '59% горький шоколад 30г', 'description' => 'С вишней и зеленой гречкой', 'price' => '225', 'image' => 'https://optim.tildacdn.com/stor3966-6535-4235-a539-623661343063/-/cover/312x390/center/center/-/format/webp/56cb52a597eb7fc7b9b0e5fff1a3c540.jpg.webp', 'category' => 'chocolate-30g', 'order' => 0),
    array('sku' => 'theobroma-30-59-date', 'name' => '59% горький шоколад 30г', 'description' => 'С цельным фиником', 'price' => '200', 'image' => 'https://optim.tildacdn.com/stor3964-3562-4034-b132-396233623766/-/cover/312x390/center/center/-/format/webp/d3df0c1d7d7b5bbce0c1f94beda7b344.jpg.webp', 'category' => 'chocolate-30g', 'order' => 1),
    array('sku' => 'theobroma-30-59-cherry-almond', 'name' => '59% горький шоколад 30г', 'description' => 'С вишней и миндалем', 'price' => '225', 'image' => 'https://optim.tildacdn.com/stor6631-6333-4263-b862-613539303832/-/cover/312x390/center/center/-/format/webp/a44b57fbb91aab5611bf3334068d5213.jpg.webp', 'category' => 'chocolate-30g', 'order' => 2),
    array('sku' => 'theobroma-30-80', 'name' => '80% горький шоколад 30г', 'description' => 'На кокосовом сахаре', 'price' => '225', 'image' => 'https://optim.tildacdn.com/stor3835-6531-4961-a334-633465393161/-/cover/312x390/center/center/-/format/webp/c5e4caa41eee5acb5cb08cd1cba8840a.jpg.webp', 'category' => 'chocolate-30g', 'order' => 3),
    array('sku' => 'theobroma-30-70', 'name' => '70% горький шоколад 30г', 'description' => 'На кокосовом сахаре', 'price' => '225', 'image' => 'https://optim.tildacdn.com/stor3536-3164-4335-a330-343232396365/-/cover/312x390/center/center/-/format/webp/bb4a92beb2360267cfc8cc171b9ec8a5.jpg.webp', 'category' => 'chocolate-30g', 'order' => 4),
    array('sku' => 'theobroma-30-hazelnut-raisin', 'name' => 'Молочный шоколад 30г', 'description' => 'С фундуком и изюмом', 'price' => '195', 'image' => 'https://optim.tildacdn.com/stor3635-3030-4531-b163-323065663666/-/cover/312x390/center/center/-/format/webp/5a718a3187c69b9830d63709b6d21f90.jpg.webp', 'category' => 'chocolate-30g', 'order' => 5),
    array('sku' => 'theobroma-30-goat', 'name' => 'Молочный шоколад 30г', 'description' => 'На козьем молоке', 'price' => '228', 'image' => 'https://optim.tildacdn.com/stor3639-6138-4933-b261-656239376431/-/cover/312x390/center/center/-/format/webp/4f58006fbad3ecd4fb522c96acaa7f35.jpg.webp', 'category' => 'chocolate-30g', 'order' => 6),
    array('sku' => 'theobroma-30-raspberry', 'name' => 'Молочный шоколад 30г', 'description' => 'С малиной', 'price' => '220', 'image' => 'https://optim.tildacdn.com/stor6562-3039-4335-b663-373061373035/-/cover/312x390/center/center/-/format/webp/0f662bea2f0f6b14df8984eaa8306440.jpg.webp', 'category' => 'chocolate-30g', 'order' => 7),
    array('sku' => 'theobroma-30-whole-hazelnut', 'name' => 'Молочный шоколад 30г', 'description' => 'С цельным фундуком', 'price' => '212', 'image' => 'https://optim.tildacdn.com/stor6632-3235-4464-a431-376135343138/-/cover/312x390/center/center/-/format/webp/7e040b2a3965fcd6c02345f0b30b5d78.jpg.webp', 'category' => 'chocolate-30g', 'order' => 8),
    array('sku' => 'theobroma-30-date-powder', 'name' => 'Молочный шоколад 30г', 'description' => 'На финиковой пудре', 'price' => '200', 'image' => 'https://optim.tildacdn.com/stor3236-6530-4565-b338-653939623961/-/cover/312x390/center/center/-/format/webp/a6ddfc576e951137e0ded425be35d84a.jpg.webp', 'category' => 'chocolate-30g', 'order' => 9),
    array('sku' => 'theobroma-cacao-100', 'name' => 'Какао порошок натуральный', 'description' => '100г', 'price' => '326', 'image' => 'https://optim.tildacdn.com/stor3032-6134-4266-a361-356436656236/-/cover/312x390/center/center/-/format/webp/e18481dd1f248ec249e12539b2c7b7bd.jpg.webp', 'category' => 'cacao', 'order' => 0),
    array('sku' => 'theobroma-cacao-200', 'name' => 'Какао порошок натуральный', 'description' => '200г', 'price' => '567', 'image' => 'https://optim.tildacdn.com/stor3834-3434-4838-a263-333234353437/-/cover/312x390/center/center/-/format/webp/879050cb73c587dcb9fd6919e3e653bb.jpg.webp', 'category' => 'cacao', 'order' => 1),
    array('sku' => 'theobroma-cacao-400', 'name' => 'Какао порошок натуральный', 'description' => '400г', 'price' => '1100', 'image' => 'https://optim.tildacdn.com/stor3533-3936-4336-b830-613936326361/-/cover/312x390/center/center/-/format/webp/923831936a63244feb2b14f49e0ed167.jpg.webp', 'category' => 'cacao', 'order' => 2),
    array('sku' => 'theobroma-chia-250', 'name' => 'Семена чиа', 'description' => '250г', 'price' => '591', 'image' => 'https://optim.tildacdn.com/stor6437-3635-4235-a632-316539346436/-/cover/312x390/center/center/-/format/webp/6b1c267ec6ff44919613cf9285efb6c3.jpg.webp', 'category' => 'chia', 'order' => 0),
    array('sku' => 'theobroma-chia-100', 'name' => 'Семена чиа', 'description' => '100г', 'price' => '236', 'image' => 'https://optim.tildacdn.com/stor3132-6365-4934-b132-313462633632/-/cover/312x390/center/center/-/format/webp/8a095d0133f93083bd2bb2bd2b6702f6.jpg.webp', 'category' => 'chia', 'order' => 1),
);

$product_sources = array(
    'theobroma-200-68-coriander' => 'https://theobroma.one/catalog/tproduct/741850665872-68-gorkii-shokolad-200g',
    'theobroma-200-65-cinnamon' => 'https://theobroma.one/catalog/tproduct/900002481752-65-gorkii-shokolad-200g',
    'theobroma-200-70' => 'https://theobroma.one/catalog/tproduct/762588251722-70-gorkii-shokolad-200g',
    'theobroma-200-80' => 'https://theobroma.one/catalog/tproduct/766598361192-80-gorkii-shokolad-200g',
    'theobroma-200-goat' => 'https://theobroma.one/catalog/tproduct/879837932952-molochnii-shokolad-200g',
    'theobroma-200-cow' => 'https://theobroma.one/catalog/tproduct/664578596402-molochnii-shokolad-200g',
    'theobroma-100-68-coriander' => 'https://theobroma.one/catalog/tproduct/359550639612-68-gorkii-shokolad-100g',
    'theobroma-100-65-cinnamon' => 'https://theobroma.one/catalog/tproduct/119818864272-65-gorkii-shokolad-100g',
    'theobroma-100-70' => 'https://theobroma.one/catalog/tproduct/137307771242-70-gorkii-shokolad-100g',
    'theobroma-100-80' => 'https://theobroma.one/catalog/tproduct/262949895262-80-gorkii-shokolad-100g',
    'theobroma-100-goat' => 'https://theobroma.one/catalog/tproduct/517210223062-molochnii-shokolad-100g',
    'theobroma-100-cow' => 'https://theobroma.one/catalog/tproduct/457538726502-molochnii-shokolad-100g',
    'theobroma-30-59-cherry-buckwheat' => 'https://theobroma.one/catalog/tproduct/929041224182-59-gorkii-shokolad-30g',
    'theobroma-30-59-date' => 'https://theobroma.one/catalog/tproduct/944014714082-59-gorkii-shokolad-30g',
    'theobroma-30-59-cherry-almond' => 'https://theobroma.one/catalog/tproduct/956722442772-59-gorkii-shokolad-30g',
    'theobroma-30-80' => 'https://theobroma.one/catalog/tproduct/629793400653-80-gorkii-shokolad-30g',
    'theobroma-30-70' => 'https://theobroma.one/catalog/tproduct/115278429153-70-gorkii-shokolad-30g',
    'theobroma-30-hazelnut-raisin' => 'https://theobroma.one/catalog/tproduct/134792997872-molochnii-shokolad-30g',
    'theobroma-30-goat' => 'https://theobroma.one/catalog/tproduct/253674692702-molochnii-shokolad-30g',
    'theobroma-30-raspberry' => 'https://theobroma.one/catalog/tproduct/174703837452-molochnii-shokolad-30g',
    'theobroma-30-whole-hazelnut' => 'https://theobroma.one/catalog/tproduct/411519368782-molochnii-shokolad-30g',
    'theobroma-30-date-powder' => 'https://theobroma.one/catalog/tproduct/658882666332-molochnii-shokolad-30g',
    'theobroma-cacao-100' => 'https://theobroma.one/catalog/tproduct/580701724902-kakao-poroshok-naturalnii',
    'theobroma-cacao-200' => 'https://theobroma.one/catalog/tproduct/281858081192-kakao-poroshok-naturalnii',
    'theobroma-cacao-400' => 'https://theobroma.one/catalog/tproduct/283000964162-kakao-poroshok-naturalnii',
    'theobroma-chia-250' => 'https://theobroma.one/catalog/tproduct/236526359272-semena-chia',
    'theobroma-chia-100' => 'https://theobroma.one/catalog/tproduct/692070558902-semena-chia',
);

/** @return array{copy:array<int,string>,details:string,benefits:array<int,array{title:string,content:string}>,marketplaces:array<string,string>}|null */
function fetch_source_product_content(string $url): ?array {
    $response = wp_remote_get($url, array('timeout' => 20));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML((string) wp_remote_retrieve_body($response), LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return null;
    }

    $xpath = new DOMXPath($document);
    $description = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' js-store-prod-all-text ')]")->item(0);
    $tabs = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' t-store__tabs__content ')]");
    $tab_titles = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' t-store__tabs__button-title ')]");
    if (!$description instanceof DOMElement || !$tabs instanceof DOMNodeList || $tabs->length === 0) {
        return null;
    }

    $marketplaces = array();
    foreach ($xpath->query('.//a[@href]', $description) as $link) {
        $label = strtolower(trim((string) $link->textContent));
        if ($label === 'wb' || $label === 'ozon') {
            $marketplaces[$label] = esc_url_raw((string) $link->getAttribute('href'));
        }
    }

    $description_html = '';
    foreach ($description->childNodes as $child) {
        $description_html .= (string) $document->saveHTML($child);
    }
    $description_html = preg_replace('~<a\b[^>]*>.*?</a>~isu', '', $description_html) ?? $description_html;
    $description_text = preg_replace('~<br\s*/?>~iu', "\n", $description_html) ?? $description_html;
    $description_text = html_entity_decode(wp_strip_all_tags($description_text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $copy = theobroma_parse_detail_copy($description_text);

    $tab_html = static function (DOMNode $node) use ($document): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= (string) $document->saveHTML($child);
        }
        return wp_kses_post($html);
    };

    $benefits = array();
    for ($index = 1; $index < $tabs->length; $index++) {
        $title = $tab_titles instanceof DOMNodeList && $tab_titles->length > $index
            ? trim((string) $tab_titles->item($index)->textContent)
            : '';
        $content = $tab_html($tabs->item($index));
        if ($title !== '' && $content !== '') {
            $benefits[] = array('title' => $title, 'content' => $content);
        }
    }

    return array(
        'copy' => $copy,
        'details' => $tab_html($tabs->item(0)),
        'benefits' => $benefits,
        'marketplaces' => $marketplaces,
    );
}

$product_content = array(
    'theobroma-200-68-coriander' => array(
        'copy' => array(
            'Мы создали рецепт этого шоколада для настоящих гурманов, ищущих чистоту и сложность вкуса.',
            'В основе – только натуральные и, самое главное, необходимые ингредиенты.',
            'Магия происходит, когда к этой классической основе мы добавляем щепотку молотого кориандра. Его природная эфирность и сложный аромат подчеркивают фруктовые и ореховые нотки какао-бобов, даря по-настоящему утонченное наслаждение.',
        ),
        'details' => '<p><strong>Состав:</strong> какао тертое натуральное, кокосовый сахар, масло какао натуральное, кориандр молотый, натуральный экстракт ванили.<br>Возможно наличие следов орехов и молочных продуктов.</p><p><strong>Пищевая ценность (100 г):</strong><br>Энергетическая ценность – 2230 кДж/560 ккал; белки – 7,5 г; жиры – 46 г; углеводы – 30 г; пищевые волокна – 10 г.</p><p><strong>Условия хранения:</strong> хранить в сухом прохладном месте при температуре от +5 до +22 градусов и относительной влажности воздуха не более 75%</p><p><strong>Срок годности</strong> – 12 месяцев.</p>',
    ),
);

foreach ($products as $order => $data) {
    $product_id = wc_get_product_id_by_sku($data['sku']);
    $product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();
    $product->set_name($data['name']);
    $product->set_slug(sanitize_title($data['sku']));
    $product->set_sku($data['sku']);
    $product->set_short_description($data['description']);
    $product->set_regular_price($data['price']);
    $product->set_price($data['price']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_category_ids(array($category_ids[$data['category'] ?? 'chocolate-200g']));
    $product->set_menu_order((int) ($data['order'] ?? $order));
    if (isset($product_content[$data['sku']])) {
        $product->update_meta_data('_theobroma_detail_copy', $product_content[$data['sku']]['copy']);
        $product->update_meta_data('_theobroma_product_details', $product_content[$data['sku']]['details']);
    }
    $source_url = $product_sources[$data['sku']] ?? '';
    if ($source_url !== '') {
        $stored_source = (string) $product->get_meta('_theobroma_source_url', true);
        $stored_copy = $product->get_meta('_theobroma_detail_copy', true);
        $stored_details = (string) $product->get_meta('_theobroma_product_details', true);
        $stored_benefits = $product->get_meta('_theobroma_product_benefits', true);
        $stored_copy_format = (int) $product->get_meta('_theobroma_detail_copy_format', true);
        if ($stored_source !== $source_url || !is_array($stored_copy) || !$stored_copy || $stored_details === '' || !is_array($stored_benefits) || $stored_copy_format < 3) {
            $source_content = fetch_source_product_content($source_url);
            if ($source_content !== null) {
                $product->update_meta_data('_theobroma_detail_copy', $source_content['copy']);
                $product->update_meta_data('_theobroma_product_details', $source_content['details']);
                $product->update_meta_data('_theobroma_product_benefits', $source_content['benefits']);
                $product->update_meta_data('_theobroma_product_benefit_title', (string) ($source_content['benefits'][0]['title'] ?? ''));
                $product->update_meta_data('_theobroma_product_benefit', (string) ($source_content['benefits'][0]['content'] ?? ''));
                $product->update_meta_data('_theobroma_marketplaces', $source_content['marketplaces']);
                $product->update_meta_data('_theobroma_source_url', $source_url);
                $product->update_meta_data('_theobroma_detail_copy_format', 3);
            } else {
                fwrite(STDERR, $data['sku'] . ": source content unavailable\n");
            }
        }
    }
    $product_id = $product->save();

    $featured_image_id = (int) $product->get_image_id();
    $detail_image_id = (int) $product->get_meta('_theobroma_product_detail_image_id', true);
    $featured_metadata = $featured_image_id ? wp_get_attachment_metadata($featured_image_id) : false;
    $detail_metadata = $detail_image_id ? wp_get_attachment_metadata($detail_image_id) : false;
    if (theobroma_product_image_needs_upgrade($featured_metadata) || theobroma_product_image_needs_upgrade($detail_metadata)) {
        $original_image_url = theobroma_tilda_original_image_url($data['image']);
        $attachment_id = media_sideload_image($original_image_url, $product_id, $data['name'], 'id');
        if (is_wp_error($attachment_id)) {
            fwrite(STDERR, $data['sku'] . ' image upgrade: ' . $attachment_id->get_error_message() . "\n");
        } else {
            $product->set_image_id((int) $attachment_id);
            $product->update_meta_data('_theobroma_product_detail_image_id', (int) $attachment_id);
            $product->update_meta_data('_theobroma_product_image_source', $original_image_url);
            $product->save();
        }
    }
    echo $data['sku'] . ':' . $product_id . "\n";
}

<?php
declare(strict_types=1);

function theobroma_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('custom-logo', array('height' => 80, 'width' => 300, 'flex-height' => true, 'flex-width' => true));
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_image_size('theobroma-media-card', 480, 360, true);
    register_nav_menus(array('primary' => __('Главное меню', 'theobroma')));
}
add_action('after_setup_theme', 'theobroma_setup');

function theobroma_assets(): void {
    $theme_dir = get_stylesheet_directory();
    wp_enqueue_style('theobroma-style', get_stylesheet_uri(), array(), (string) filemtime($theme_dir . '/style.css'));
    wp_enqueue_script(
        'theobroma-site-header',
        get_template_directory_uri() . '/assets/js/site-header.js',
        array(),
        (string) filemtime($theme_dir . '/assets/js/site-header.js'),
        array('strategy' => 'defer', 'in_footer' => true)
    );
}
add_action('wp_enqueue_scripts', 'theobroma_assets');

function theobroma_preload_critical_fonts(): void {
    $font_base = get_template_directory_uri() . '/assets/fonts/';
    printf(
        '<link rel="icon" href="%s" type="image/webp">' . "\n",
        esc_url(get_template_directory_uri() . '/assets/images/logo.webp')
    );
    foreach (array('montserrat-cyrillic.woff2', 'montserrat-latin.woff2', 'cormorant-cyrillic-variable.woff2') as $font) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url($font_base . $font)
        );
    }
}
add_action('wp_head', 'theobroma_preload_critical_fonts', 1);

function theobroma_mark_critical_type_loading(): void {
    ?>
    <script>document.documentElement.classList.add('fonts-loading');</script>
    <?php
}
add_action('wp_head', 'theobroma_mark_critical_type_loading', 2);

function theobroma_stabilize_critical_type(): void {
    ?>
    <script>(function(){var root=document.documentElement;var reveal=function(){root.classList.remove('fonts-loading');};Promise.all([document.fonts.load('400 11px Montserrat','Бесплатная доставка от 2500 рублей'),document.fonts.load('400 16px Montserrat','Каталог Рецепты Маркетплейсы Где купить Сотрудничество Контакты'),document.fonts.load('400 18px Montserrat','Необычный, кусковой, пористый шоколад'),document.fonts.load('500 16px Montserrat','В каталог'),document.fonts.load('400 75px Cormorant','АБСОЛЮТНО НАТУРАЛЬНЫЙ ШОКОЛАД')]).then(function(){return document.fonts.ready;}).then(reveal,reveal);}());</script>
    <?php
}
add_action('wp_head', 'theobroma_stabilize_critical_type', 99);
add_filter('show_admin_bar', '__return_false');

function theobroma_content(string $key): string {
    $defaults = array(
        'shipping_text' => 'Бесплатная доставка от 2500 рублей',
        'hero_line_1' => 'Абсолютно',
        'hero_line_2' => 'натуральный',
        'hero_line_3' => 'шоколад',
        'hero_subtitle' => 'Необычный, кусковой, пористый шоколад',
        'products_accent' => 'Продукция',
        'products_heading' => 'Пища богов',
        'products_note' => 'Натуральный состав, сложный вкус и привычка выбирать лучшее каждый день.',
        'story_heading' => "Theobroma — абсолютно\nнатуральный шоколад",
        'story_text' => "Компания Theobroma Пища Богов — российский бренд,\nкоторый бережно сочетает вековые кулинарные\nтрадиции с современными технологиями в создании\nнатурального шоколада и какао.",
        'contact_heading' => 'Остались',
        'contact_accent' => 'вопросы?',
        'contact_success' => 'Спасибо! Мы скоро свяжемся с вами.',
        'value_1_title' => 'Признание экспертов',
        'value_1_text_1' => 'Продукт года по версии WorldFood Moscow',
        'value_1_text_2' => 'Лауреат премии «Здоровое питание» (2015)',
        'value_2_title' => 'Натуральный состав',
        'value_2_text' => 'Бережно сохраняем природные свойства какао и используем только чистые ингредиенты без лишних добавок.',
        'value_3_title' => 'Без белого сахара',
        'value_3_text' => 'Мы используем кокосовый сахар — природный источник минералов: калия, магния, цинка и железа.',
        'reviews_accent' => 'Отзывы',
        'reviews_heading' => 'о наших продуктах',
        'footer_address' => "Адрес фабрики:\nМосковская обл.,\nНаро-Фоминский г.о.,\nд.Софьино 230А. 143345",
        'footer_phone_1' => '+7 499 755 54 90',
        'footer_phone_2' => '+7 800 444 70 54',
        'footer_info_email' => 'info@theobroma.msk.ru',
        'footer_info_note' => "Коммерческие предложения\nи любые другие вопросы",
        'footer_opt_email' => 'opt@theobroma.msk.ru',
        'footer_opt_note' => 'Запросы по оптовым покупкам',
        'footer_press_email' => 'press@theobroma.msk.ru',
        'footer_press_note' => 'По вопросам сотрудничества со СМИ',
        'footer_company' => "ООО «Пища Богов»\nИНН 7729769598\nОГРН 1147746398612",
        'footer_bank' => "Банковские реквизиты:\nРасчетный счет 40702810902500137198 в ООО \"Банк Точка\"\nБИК 044525104\nк/с 30101810745374525104",
        'social_vk' => 'https://vk.com/theobroma.chocolate',
        'social_telegram' => 'https://t.me/teobroma_chocolate',
        'social_whatsapp' => 'https://wa.me/79257555626',
        'social_dzen' => 'https://dzen.ru/pishchabogov',
    );
    $settings = get_option('theobroma_content_settings', array());
    return isset($settings[$key]) && is_string($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : ($defaults[$key] ?? '');
}

function theobroma_page_url(string $title): string {
    $known_slugs = array(
        'Политика конфиденциальности' => 'policy',
        'Согласие на обработку персональных данных' => 'consent',
        'Пользовательское соглашение' => 'agreement',
        'Публичная оферта' => 'oferta',
        'Медиа' => 'media',
    );
    if (isset($known_slugs[$title])) {
        $page = get_page_by_path($known_slugs[$title], OBJECT, 'page');
        if ($page instanceof WP_Post) {
            return (string) get_permalink($page);
        }
    }
    $page = get_page_by_title($title, OBJECT, 'page');
    return $page ? (string) get_permalink($page) : home_url('/');
}

function theobroma_register_contact_requests(): void {
    register_post_type('contact_request', array(
        'labels' => array('name' => __('Заявки', 'theobroma'), 'singular_name' => __('Заявка', 'theobroma')),
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-email-alt',
        'supports' => array('title', 'editor'),
    ));
}
add_action('init', 'theobroma_register_contact_requests');

function theobroma_register_recipes(): void {
    register_post_type('theobroma_recipe', array(
        'labels' => array(
            'name' => __('Рецепты', 'theobroma'),
            'singular_name' => __('Рецепт', 'theobroma'),
            'add_new_item' => __('Добавить рецепт', 'theobroma'),
            'edit_item' => __('Редактировать рецепт', 'theobroma'),
        ),
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => array('slug' => 'recipe', 'with_front' => false),
        'menu_icon' => 'dashicons-food',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
    ));
}
add_action('init', 'theobroma_register_recipes');

function theobroma_register_reviews(): void {
    register_post_type('theobroma_review', array(
        'labels' => array(
            'name' => __('Отзывы сайта', 'theobroma'),
            'singular_name' => __('Отзыв', 'theobroma'),
            'add_new_item' => __('Добавить отзыв', 'theobroma'),
            'edit_item' => __('Редактировать отзыв', 'theobroma'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-format-quote',
        'supports' => array('title', 'editor', 'page-attributes'),
    ));
}
add_action('init', 'theobroma_register_reviews');

/**
 * Return a structured recipe field saved as JSON in post meta.
 *
 * @return array<int, array<string, string>>
 */
function theobroma_recipe_rows(int $post_id, string $key): array {
    $value = get_post_meta($post_id, $key, true);
    if (!is_string($value) || $value === '') {
        return array();
    }
    $rows = json_decode($value, true);
    return is_array($rows) ? $rows : array();
}

function theobroma_recipe_url(string $slug): string {
    $recipe = get_page_by_path($slug, OBJECT, 'theobroma_recipe');
    return $recipe instanceof WP_Post ? (string) get_permalink($recipe) : theobroma_page_url('Рецепты');
}

function theobroma_catalog_layout(): void {
    if (function_exists('is_shop') && (is_shop() || is_product_category())) {
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        remove_action('woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
    }
}
add_action('wp', 'theobroma_catalog_layout');

function theobroma_catalog_products(WP_Query $query): void {
    if (!is_admin() && $query->is_main_query() && function_exists('is_shop') && is_shop()) {
        $groups = array('chocolate-200g', 'chocolate-100g', 'chocolate-30g', 'cacao', 'chia');
        $requested_group = sanitize_key(wp_unslash($_GET['product_group'] ?? 'chocolate-200g'));
        $query->set('product_cat', in_array($requested_group, $groups, true) ? $requested_group : 'chocolate-200g');
        $query->set('posts_per_page', 12);
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'theobroma_catalog_products');

function theobroma_catalog_excerpt(): void {
    global $product;
    if ($product instanceof WC_Product && $product->get_short_description()) {
        $description = wp_strip_all_tags($product->get_short_description());
        $description = preg_replace('/\b(На|на|с|и|в|по)\s+/u', '$1&nbsp;', $description) ?? $description;
        echo '<p class="catalog-product-description">' . wp_kses($description, array()) . '</p>';
    }
}
add_action('woocommerce_after_shop_loop_item_title', 'theobroma_catalog_excerpt', 6);

// Tilda's catalogue cards use the original 312x390 portrait asset. Using the
// square WooCommerce thumbnail here changes the crop before CSS can size it.
add_filter('single_product_archive_thumbnail_size', static fn(): string => 'full');

function theobroma_loop_button_text(string $text, WC_Product $product): string {
    return (function_exists('is_shop') && is_shop()) ? 'Добавить в корзину' : $text;
}
add_filter('woocommerce_product_add_to_cart_text', 'theobroma_loop_button_text', 10, 2);
add_filter('woocommerce_product_single_add_to_cart_text', static fn(): string => 'Добавить в корзину');

add_filter('woocommerce_currency_symbol', static fn(string $symbol, string $currency): string => $currency === 'RUB' ? 'р.' : $symbol, 10, 2);
add_filter('woocommerce_price_format', static fn(string $format): string => '%2$s%1$s');
add_filter('wc_get_price_decimals', static fn(int $decimals): int => 0);
add_filter('wc_get_price_thousand_separator', static fn(string $separator): string => ' ');

function theobroma_cart_count_fragment(array $fragments): array {
    $count = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $fragments['.floating-actions .cart-count'] = '<span class="cart-count">(' . esc_html((string) $count) . ')</span>';
    return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'theobroma_cart_count_fragment');

function theobroma_frontend_product_title(string $title, int $post_id): string {
    if (is_admin() || get_post_type($post_id) !== 'product') {
        return $title;
    }

    $title = mb_strtoupper($title, 'UTF-8');
    return (string) preg_replace('/Г$/u', 'г', $title);
}
add_filter('the_title', 'theobroma_frontend_product_title', 10, 2);

function theobroma_handle_contact_request(): void {
    check_admin_referer('theobroma_contact', 'theobroma_contact_nonce');
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $consent = sanitize_text_field(wp_unslash($_POST['consent'] ?? ''));
    if ($name === '' || $phone === '' || $consent !== '1') {
        wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer() ?: home_url('/')));
        exit;
    }
    $request_id = wp_insert_post(
        array('post_type' => 'contact_request', 'post_status' => 'publish', 'post_title' => $name . ' — ' . $phone, 'post_content' => $message),
        true
    );
    if (is_wp_error($request_id)) {
        wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer() ?: home_url('/')) . '#contacts');
        exit;
    }
    wp_safe_redirect(add_query_arg('contact', 'sent', wp_get_referer() ?: home_url('/')) . '#contacts');
    exit;
}
add_action('admin_post_nopriv_theobroma_contact', 'theobroma_handle_contact_request');
add_action('admin_post_theobroma_contact', 'theobroma_handle_contact_request');

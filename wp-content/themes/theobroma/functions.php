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

function theobroma_redirect_legacy_wordpress_routes(): void {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $target = '';
    $request_path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if ($request_path === 'buy-old') {
        $target = theobroma_page_url('Где купить');
    } elseif (is_page()) {
        $page = get_queried_object();
        $page_redirects = array(
            'sample-page' => home_url('/'),
            'offer' => home_url('/oferta/'),
            'policy-2' => home_url('/policy/'),
        );
        if ($page instanceof WP_Post && isset($page_redirects[$page->post_name])) {
            $target = $page_redirects[$page->post_name];
        }
    } elseif (is_author()) {
        $target = home_url('/');
    } elseif (is_single()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post && rawurldecode($post->post_name) === 'привет-мир') {
            $target = home_url('/');
        }
    } elseif (is_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && rawurldecode($term->slug) === 'без-рубрики') {
            $target = theobroma_page_url('Медиа');
        }
    }

    if ($target !== '') {
        wp_safe_redirect($target, 301, 'Theobroma');
        exit;
    }
}
add_action('template_redirect', 'theobroma_redirect_legacy_wordpress_routes');

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

    if (is_page('buy')) {
        wp_enqueue_script(
            'theobroma-buy-tabs',
            get_template_directory_uri() . '/assets/js/buy-tabs.js',
            array(),
            (string) filemtime($theme_dir . '/assets/js/buy-tabs.js'),
            array('strategy' => 'defer', 'in_footer' => true)
        );
    }

    if (class_exists('WooCommerce')) {
        foreach (array('wc-add-to-cart', 'wc-country-select', 'wc-address-i18n', 'wc-checkout') as $handle) {
            wp_enqueue_script($handle);
        }

        wp_enqueue_script(
            'theobroma-commerce-modals',
            get_template_directory_uri() . '/assets/js/commerce-modals.js',
            array('jquery', 'wc-add-to-cart', 'wc-country-select', 'wc-address-i18n', 'wc-checkout'),
            (string) filemtime($theme_dir . '/assets/js/commerce-modals.js'),
            array('strategy' => 'defer', 'in_footer' => true)
        );
        wp_localize_script('theobroma-commerce-modals', 'theobromaCommerce', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'wcAjaxUrl' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('%%endpoint%%') : '',
            'nonce' => wp_create_nonce('theobroma_commerce'),
            'cartUrl' => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'shopUrl' => wc_get_page_permalink('shop'),
            'wishlistIds' => array_values(array_map('absint', (array) apply_filters('theobroma_wishlist_ids', array()))),
            'wishlistLoggedIn' => is_user_logged_in(),
        ));

        if (!is_user_logged_in()) {
            wp_enqueue_script(
                'theobroma-account-modal',
                get_template_directory_uri() . '/assets/js/account-modal.js',
                array(),
                (string) filemtime($theme_dir . '/assets/js/account-modal.js'),
                array('strategy' => 'defer', 'in_footer' => true)
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'theobroma_assets');

function theobroma_preload_critical_fonts(): void {
    $font_base = get_template_directory_uri() . '/assets/fonts/';
    printf(
        '<link rel="icon" href="%s" type="image/webp">' . "\n",
        esc_url(get_template_directory_uri() . '/assets/images/logo.webp')
    );
    foreach (array('montserrat-cyrillic.woff2', 'cormorant-cyrillic-variable.woff2') as $font) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url($font_base . $font)
        );
    }
    if (is_front_page()) {
        foreach (array('hero-bg-original.jpg', 'hero-chocolate-original.webp') as $image) {
            printf(
                '<link rel="preload" href="%s" as="image" fetchpriority="high">' . "\n",
                esc_url(get_template_directory_uri() . '/assets/images/' . $image)
            );
        }
    }
}
add_action('wp_head', 'theobroma_preload_critical_fonts', 9);

add_filter('show_admin_bar', '__return_false');

/** @return WP_Post[] */
function theobroma_related_media_posts(int $post_id, int $limit = 3): array {
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'post' || $limit < 1) {
        return array();
    }

    $category_ids = wp_get_post_categories($post_id, array('fields' => 'ids'));
    $query = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post__not_in' => array($post_id),
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    );
    if ($category_ids !== array()) {
        $query['category__in'] = array_map('absint', $category_ids);
    }

    return array_values(array_filter(
        get_posts($query),
        static fn($related): bool => $related instanceof WP_Post
    ));
}

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
        'corporate_hero_title' => 'Корпоративные подарки',
        'corporate_hero_accent' => 'со вкусом заботы',
        'corporate_intro' => 'Создадим шоколадные подарки для клиентов, команды и партнёров: от готовых наборов до брендированных решений под ваш повод.',
        'corporate_branding_1_title' => 'Фирменная упаковка',
        'corporate_branding_1_text' => 'Подберём коробку, ленту и цветовую гамму под стиль вашей компании.',
        'corporate_branding_2_title' => 'Открытки и вкладыши',
        'corporate_branding_2_text' => 'Добавим логотип, персональное обращение или поздравление для получателя.',
        'corporate_branding_3_title' => 'Состав набора',
        'corporate_branding_3_text' => 'Соберём ассортимент из доступных вкусов и форматов шоколада Theobroma.',
        'corporate_case_1_title' => 'Подарки клиентам',
        'corporate_case_1_text' => 'Брендированный набор для благодарности, события или завершения проекта.',
        'corporate_case_2_title' => 'Забота о команде',
        'corporate_case_2_text' => 'Подарочные комплекты для праздников, welcome-наборов и внутренних событий.',
        'corporate_case_3_title' => 'Партнёрские события',
        'corporate_case_3_text' => 'Компактные подарки для конференций, встреч и деловых мероприятий.',
        'corporate_minimum' => 'Минимальный тираж и стоимость зависят от состава, упаковки и брендирования. После заявки менеджер подтвердит доступный объём, сроки и логистику.',
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

/**
 * Keep the classic checkout inside the cart modal as concise as the original
 * storefront while still letting WooCommerce own validation and order data.
 */
function theobroma_checkout_fields(array $fields): array {
    $allowed = array('billing_city', 'billing_first_name', 'billing_phone', 'billing_email');
    foreach (array_keys($fields['billing'] ?? array()) as $key) {
        if (!in_array($key, $allowed, true)) {
            unset($fields['billing'][$key]);
        }
    }

    $field_config = array(
        'billing_city' => array('label' => 'Город', 'placeholder' => '', 'priority' => 10),
        'billing_first_name' => array('label' => '', 'placeholder' => 'Имя', 'priority' => 20),
        'billing_phone' => array('label' => '', 'placeholder' => '+7 (000) 000-00-00', 'priority' => 30, 'required' => true),
        'billing_email' => array('label' => '', 'placeholder' => 'Email', 'priority' => 40),
    );
    foreach ($field_config as $key => $config) {
        if (isset($fields['billing'][$key])) {
            $fields['billing'][$key] = array_merge($fields['billing'][$key], $config, array('class' => array('form-row-wide')));
        }
    }

    $fields['shipping'] = array();
    $fields['account'] = array();
    $fields['order'] = array();
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'theobroma_checkout_fields', 20);
add_filter('woocommerce_checkout_registration_enabled', '__return_false');
add_filter('woocommerce_order_button_text', static fn(): string => 'заказать');
add_filter('woocommerce_checkout_privacy_policy_text', '__return_empty_string');

function theobroma_checkout_consent(): void {
    $agreement_url = theobroma_page_url('Пользовательское соглашение');
    $policy_url = theobroma_page_url('Политика конфиденциальности');
    ?>
    <p class="commerce-checkout-consent form-row validate-required">
        <label>
            <input type="checkbox" name="theobroma_privacy_consent" value="1" required>
            <span>Отправляя форму я даю <a href="<?php echo esc_url($agreement_url); ?>">согласие</a> на <a href="<?php echo esc_url($policy_url); ?>">обработку персональных данных</a></span>
        </label>
    </p>
    <?php
}
add_action('woocommerce_checkout_before_terms_and_conditions', 'theobroma_checkout_consent');

function theobroma_validate_checkout_consent(): void {
    if (sanitize_text_field(wp_unslash($_POST['theobroma_privacy_consent'] ?? '')) !== '1') {
        wc_add_notice('Подтвердите согласие на обработку персональных данных.', 'error');
    }
}
add_action('woocommerce_checkout_process', 'theobroma_validate_checkout_consent');

add_filter('woocommerce_get_terms_and_conditions_checkbox_text', static function (): string {
    $offer_url = theobroma_page_url('Публичная оферта');
    return 'Отправляя форму я соглашаюсь с <a href="' . esc_url($offer_url) . '">публичной офертой</a>';
});

function theobroma_checkout_total(): void {
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    ?>
    <div class="commerce-checkout-total"><span>Итоговая сумма:</span><strong><?php echo wp_kses_post(WC()->cart->get_total()); ?></strong></div>
    <?php
}
add_action('woocommerce_checkout_after_terms_and_conditions', 'theobroma_checkout_total');

function theobroma_checkout_afterword(): void {
    echo '<p class="commerce-checkout-afterword">После оформления заказа с вами свяжется наш менеджер для уточнения деталей заказа и доставки. Пожалуйста, будьте на связи, чтобы мы могли быстрее обработать ваш заказ.</p>';
}
add_action('woocommerce_review_order_after_submit', 'theobroma_checkout_afterword');

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

function theobroma_product_modal_title(WC_Product $product): string {
    $title = $product->get_name();
    if (has_term(array('cacao', 'chia'), 'product_cat', $product->get_id())) {
        return $title;
    }

    return theobroma_frontend_product_title($title, $product->get_id());
}

function theobroma_product_benefit_title(WC_Product $product): string {
    $title = trim((string) $product->get_meta('_theobroma_product_benefit_title', true));
    if ($title !== '') {
        return $title;
    }

    return strpos($product->get_sku(), 'theobroma-chia-') === 0
        ? 'Полезные свойства семян чиа'
        : 'Польза кокосового сахара';
}

function theobroma_handle_contact_request(): void {
    check_admin_referer('theobroma_contact', 'theobroma_contact_nonce');
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $request_type = sanitize_key(wp_unslash($_POST['request_type'] ?? 'contact'));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $honeypot = sanitize_text_field(wp_unslash($_POST['theobroma_website'] ?? ''));
    $started_at = absint($_POST['theobroma_form_started'] ?? 0);
    $consent = sanitize_text_field(wp_unslash($_POST['consent'] ?? ''));
    if ($name === '' || $phone === '' || $consent !== '1' || $honeypot !== '' || $started_at === 0 || (time() - $started_at) < 3 || ($request_type === 'corporate_gift' && !is_email($email))) {
        wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer() ?: home_url('/')));
        exit;
    }
    $request_id = wp_insert_post(
        array('post_type' => 'contact_request', 'post_status' => 'publish', 'post_title' => $name . ' — ' . $phone, 'post_content' => $message),
        true
    );
    if (is_wp_error($request_id)) {
        wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer() ?: home_url('/')) . '#contact-form');
        exit;
    }
    if ($request_type === 'corporate_gift') {
        $details = array_filter(array(
            'Компания: ' . sanitize_text_field(wp_unslash($_POST['company'] ?? '')),
            'Тип подарка: ' . sanitize_text_field(wp_unslash($_POST['gift_type'] ?? '')),
            'Тираж: ' . sanitize_text_field(wp_unslash($_POST['volume'] ?? '')),
            'Брендирование: ' . sanitize_text_field(wp_unslash($_POST['branding'] ?? '')),
        ));
        update_post_meta((int) $request_id, '_theobroma_request_type', 'corporate_gift');
        update_post_meta((int) $request_id, '_theobroma_request_email', $email);
        wp_update_post(array('ID' => (int) $request_id, 'post_content' => trim(implode("\n", $details) . "\n\n" . $message)));
        wp_mail(get_option('admin_email'), 'Корпоративная заявка Theobroma', implode("\n", array_merge(array('Имя: ' . $name, 'Телефон: ' . $phone, 'E-mail: ' . $email), $details, array('Комментарий: ' . $message))));
    }
    wp_safe_redirect(add_query_arg('contact', 'sent', wp_get_referer() ?: home_url('/')) . '#contact-form');
    exit;
}

function theobroma_related_product_ids(WC_Product $product, int $limit = 4): array {
    $ids = array_map('absint', wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        'return' => 'ids',
        'exclude' => array($product->get_id()),
    )));
    shuffle($ids);

    return array_slice($ids, 0, $limit);
}
add_action('admin_post_nopriv_theobroma_contact', 'theobroma_handle_contact_request');
add_action('admin_post_theobroma_contact', 'theobroma_handle_contact_request');

function theobroma_contact_antispam_fields(): void {
    printf('<input type="hidden" name="theobroma_form_started" value="%d"><p class="theobroma-honeypot" aria-hidden="true"><label>Website<input type="text" name="theobroma_website" tabindex="-1" autocomplete="off"></label></p>', time());
}

/**
 * Shared, progressively enhanced commerce modal shell.
 *
 * Product pages remain valid standalone URLs for SEO and no-JS clients. The
 * catalogue upgrades those URLs to an overlay and keeps browser history in
 * sync, while cart and checkout content is rendered by WooCommerce itself.
 */
function theobroma_render_commerce_modal_root(): void {
    if (is_admin() || !class_exists('WooCommerce')) {
        return;
    }
    ?>
    <div class="commerce-modal" id="commerce-modal" hidden aria-hidden="true">
        <div class="commerce-modal-backdrop" data-commerce-close></div>
        <section class="commerce-modal-panel" role="dialog" aria-modal="true" aria-live="polite" aria-label="<?php esc_attr_e('Информация о товаре', 'theobroma'); ?>">
            <button class="commerce-modal-back" type="button" data-commerce-close><?php esc_html_e('← Назад', 'theobroma'); ?></button>
            <button class="commerce-modal-close" type="button" data-commerce-close aria-label="<?php esc_attr_e('Закрыть', 'theobroma'); ?>"></button>
            <div class="commerce-modal-status" role="status"><?php esc_html_e('Загрузка…', 'theobroma'); ?></div>
            <div class="commerce-modal-content">
                <form class="checkout woocommerce-checkout theobroma-checkout-anchor" method="post" hidden></form>
            </div>
        </section>
    </div>
    <?php
}
add_action('wp_footer', 'theobroma_render_commerce_modal_root', 5);

/**
 * Keep authentication native to WooCommerce while presenting it with the same
 * progressively enhanced side panel as the source storefront.
 */
function theobroma_render_account_modal(): void {
    if (is_admin() || is_user_logged_in() || !class_exists('WooCommerce')) {
        return;
    }

    get_template_part('template-parts/account/modal');
}
add_action('wp_footer', 'theobroma_render_account_modal', 6);

function theobroma_enable_customer_registration(mixed $value): string {
    return 'yes';
}
add_filter('pre_option_woocommerce_enable_myaccount_registration', 'theobroma_enable_customer_registration');

function theobroma_require_customer_password(mixed $value): string {
    return 'no';
}
add_filter('pre_option_woocommerce_registration_generate_password', 'theobroma_require_customer_password');

function theobroma_generate_customer_username(mixed $value): string {
    return 'yes';
}
add_filter('pre_option_woocommerce_registration_generate_username', 'theobroma_generate_customer_username');

function theobroma_allow_customer_email_login(array $credentials): array {
    $login = isset($credentials['user_login']) ? trim((string) $credentials['user_login']) : '';
    if (is_email($login)) {
        $user = get_user_by('email', $login);
        if ($user instanceof WP_User) {
            $credentials['user_login'] = $user->user_login;
        }
    }
    return $credentials;
}
add_filter('woocommerce_login_credentials', 'theobroma_allow_customer_email_login');

function theobroma_account_menu_items(array $items): array {
    return array_filter(array(
        'dashboard'       => __('Главная', 'theobroma'),
        'orders'          => __('Заказы', 'theobroma'),
        'bonuses'         => __('Бонусы', 'theobroma'),
        'edit-address'    => __('Адреса', 'theobroma'),
        'edit-account'    => __('Профиль', 'theobroma'),
        'customer-logout' => $items['customer-logout'] ?? __('Выйти', 'theobroma'),
    ));
}
add_filter('woocommerce_account_menu_items', 'theobroma_account_menu_items');

function theobroma_cart_modal_html(): string {
    ob_start();
    get_template_part('template-parts/commerce/cart-modal');
    return (string) ob_get_clean();
}

function theobroma_ajax_cart_modal(): void {
    check_ajax_referer('theobroma_commerce', 'nonce');
    if (!function_exists('WC')) {
        wp_send_json_error(array('message' => __('Корзина недоступна.', 'theobroma')), 503);
    }
    if (!WC()->cart) {
        wc_load_cart();
    }
    wp_send_json_success(array(
        'html' => theobroma_cart_modal_html(),
        'count' => WC()->cart->get_cart_contents_count(),
    ));
}
add_action('wp_ajax_theobroma_cart_modal', 'theobroma_ajax_cart_modal');
add_action('wp_ajax_nopriv_theobroma_cart_modal', 'theobroma_ajax_cart_modal');

function theobroma_ajax_cart_update(): void {
    check_ajax_referer('theobroma_commerce', 'nonce');
    if (!function_exists('WC')) {
        wp_send_json_error(array('message' => __('Корзина недоступна.', 'theobroma')), 503);
    }
    if (!WC()->cart) {
        wc_load_cart();
    }

    $clear = sanitize_text_field(wp_unslash($_POST['clear'] ?? '')) === '1';
    if ($clear) {
        WC()->cart->empty_cart();
    } else {
        $cart_key = sanitize_text_field(wp_unslash($_POST['cart_key'] ?? ''));
        $quantity = max(0, absint($_POST['quantity'] ?? 0));
        if ($cart_key === '' || !isset(WC()->cart->get_cart()[$cart_key])) {
            wp_send_json_error(array('message' => __('Товар не найден в корзине.', 'theobroma')), 404);
        }
        WC()->cart->set_quantity($cart_key, $quantity, true);
    }

    WC()->cart->calculate_totals();
    wp_send_json_success(array(
        'html' => theobroma_cart_modal_html(),
        'count' => WC()->cart->get_cart_contents_count(),
    ));
}
add_action('wp_ajax_theobroma_cart_update', 'theobroma_ajax_cart_update');
add_action('wp_ajax_nopriv_theobroma_cart_update', 'theobroma_ajax_cart_update');

<?php
/**
 * Plugin Name: Theobroma — управление сайтом
 * Description: Удобное редактирование контента и быстрые переходы для сайта Theobroma.
 * Version: 1.0.0
 * Author: Theobroma
 */

defined('ABSPATH') || exit;

final class Theobroma_Admin_Tools {
    private const NONCE_ACTION = 'theobroma_save_fields';
    private const NONCE_NAME = 'theobroma_fields_nonce';

    public static function boot(): void {
        add_action('admin_menu', array(self::class, 'register_content_hub'));
        add_action('admin_post_theobroma_save_content_settings', array(self::class, 'save_content_settings'));
        add_action('wp_dashboard_setup', array(self::class, 'register_dashboard_widget'));
        add_action('add_meta_boxes_product', array(self::class, 'register_product_box'));
        add_action('add_meta_boxes_post', array(self::class, 'register_media_box'));
        add_action('add_meta_boxes_theobroma_recipe', array(self::class, 'register_recipe_box'));
        add_action('save_post_product', array(self::class, 'save_product_fields'));
        add_action('save_post_post', array(self::class, 'save_media_fields'));
        add_action('save_post_theobroma_recipe', array(self::class, 'save_recipe_fields'));
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_recipe_assets'));
        add_filter('use_block_editor_for_post_type', array(self::class, 'use_classic_recipe_editor'), 10, 2);
        add_filter('wp_insert_post_data', array(self::class, 'append_new_recipe'), 10, 2);
        add_filter('manage_product_posts_columns', array(self::class, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array(self::class, 'render_product_column'), 10, 2);
    }

    public static function register_content_hub(): void {
        add_menu_page('Контент сайта', 'Контент сайта', 'edit_posts', 'theobroma-content', array(self::class, 'render_content_hub'), 'dashicons-store', 3);
        add_submenu_page('theobroma-content', 'Общие блоки', 'Общие блоки', 'manage_options', 'theobroma-settings', array(self::class, 'render_content_settings'));
    }

    public static function render_content_settings(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $groups = array(
            'Шапка и первый экран' => array(
                'shipping_text' => array('Текст верхней плашки', 'text'),
                'hero_line_1' => array('Hero: строка 1', 'text'),
                'hero_line_2' => array('Hero: строка 2', 'text'),
                'hero_line_3' => array('Hero: строка 3', 'text'),
                'hero_subtitle' => array('Hero: подзаголовок', 'text'),
                'products_accent' => array('Продукция: акцент заголовка', 'text'),
                'products_heading' => array('Продукция: продолжение заголовка', 'text'),
                'products_note' => array('Продукция: подзаголовок', 'text'),
                'story_heading' => array('Заголовок о компании', 'textarea'),
                'story_text' => array('Текст о компании', 'textarea'),
                'value_1_title' => array('Преимущество 1: заголовок', 'text'),
                'value_1_text_1' => array('Преимущество 1: строка 1', 'text'),
                'value_1_text_2' => array('Преимущество 1: строка 2', 'text'),
                'value_2_title' => array('Преимущество 2: заголовок', 'text'),
                'value_2_text' => array('Преимущество 2: текст', 'text'),
                'value_3_title' => array('Преимущество 3: заголовок', 'text'),
                'value_3_text' => array('Преимущество 3: текст', 'text'),
                'reviews_accent' => array('Отзывы: акцент заголовка', 'text'),
                'reviews_heading' => array('Отзывы: продолжение заголовка', 'text'),
            ),
            'Форма связи' => array(
                'contact_heading' => array('Заголовок', 'text'),
                'contact_accent' => array('Акцентная часть', 'text'),
                'contact_success' => array('Сообщение после отправки', 'text'),
            ),
            'Футер' => array(
                'footer_address' => array('Адрес фабрики', 'textarea'),
                'footer_phone_1' => array('Телефон 1', 'text'),
                'footer_phone_2' => array('Телефон 2', 'text'),
                'footer_info_email' => array('Общий email', 'email'),
                'footer_info_note' => array('Подпись общего email', 'textarea'),
                'footer_opt_email' => array('Email оптовых продаж', 'email'),
                'footer_opt_note' => array('Подпись оптового email', 'textarea'),
                'footer_press_email' => array('Email для СМИ', 'email'),
                'footer_press_note' => array('Подпись email для СМИ', 'textarea'),
                'footer_company' => array('Реквизиты компании', 'textarea'),
                'footer_bank' => array('Банковские реквизиты', 'textarea'),
                'social_vk' => array('ВКонтакте', 'url'),
                'social_telegram' => array('Telegram', 'url'),
                'social_whatsapp' => array('WhatsApp', 'url'),
                'social_dzen' => array('Дзен', 'url'),
            ),
        );
        echo '<div class="wrap"><h1>Общие блоки сайта</h1><p>Эти значения используются на всех страницах. Пустое поле возвращает исходный текст.</p>';
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="theobroma_save_content_settings">';
        wp_nonce_field('theobroma_save_content_settings');
        foreach ($groups as $title => $fields) {
            echo '<h2>' . esc_html($title) . '</h2><table class="form-table"><tbody>';
            foreach ($fields as $key => $definition) {
                $value = function_exists('theobroma_content') ? theobroma_content($key) : '';
                echo '<tr><th scope="row"><label for="theobroma_' . esc_attr($key) . '">' . esc_html($definition[0]) . '</label></th><td>';
                if ($definition[1] === 'textarea') {
                    echo '<textarea class="large-text" rows="4" id="theobroma_' . esc_attr($key) . '" name="settings[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
                } else {
                    echo '<input class="regular-text" type="' . esc_attr($definition[1]) . '" id="theobroma_' . esc_attr($key) . '" name="settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        submit_button('Сохранить изменения');
        echo '</form></div>';
    }

    public static function save_content_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.');
        }
        check_admin_referer('theobroma_save_content_settings');
        $input = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        $multiline = array('story_heading', 'story_text', 'footer_address', 'footer_info_note', 'footer_opt_note', 'footer_press_note', 'footer_company', 'footer_bank');
        $emails = array('footer_info_email', 'footer_opt_email', 'footer_press_email');
        $urls = array('social_vk', 'social_telegram', 'social_whatsapp', 'social_dzen');
        $clean = array();
        foreach ($input as $key => $value) {
            $key = sanitize_key($key);
            if (!is_string($value)) {
                continue;
            }
            if (in_array($key, $multiline, true)) {
                $clean[$key] = sanitize_textarea_field($value);
            } elseif (in_array($key, $emails, true)) {
                $clean[$key] = sanitize_email($value);
            } elseif (in_array($key, $urls, true)) {
                $clean[$key] = esc_url_raw($value);
            } else {
                $clean[$key] = sanitize_text_field($value);
            }
        }
        update_option('theobroma_content_settings', $clean, false);
        wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=theobroma-settings')));
        exit;
    }

    public static function use_classic_recipe_editor(bool $use_block_editor, string $post_type): bool {
        return $post_type === 'theobroma_recipe' ? false : $use_block_editor;
    }

    public static function append_new_recipe(array $data, array $postarr): array {
        if (($data['post_type'] ?? '') !== 'theobroma_recipe' || !empty($postarr['ID']) || (int) ($data['menu_order'] ?? 0) !== 0) {
            return $data;
        }
        global $wpdb;
        $maximum = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')", 'theobroma_recipe'));
        $data['menu_order'] = $maximum + 1;
        return $data;
    }

    public static function render_content_hub(): void {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $links = array(
            array('Товары', admin_url('edit.php?post_type=product'), 'Цены, карточки, составы и ссылки на маркетплейсы'),
            array('Рецепты', admin_url('edit.php?post_type=theobroma_recipe'), 'Карточки, ингредиенты, шаги, фотографии и связанные товары'),
            array('Общие блоки', admin_url('admin.php?page=theobroma-settings'), 'Верхняя плашка, первый экран, форма связи, контакты и футер'),
            array('Отзывы', admin_url('edit.php?post_type=theobroma_review'), 'Отзывы на главной: имя, текст, дата публикации и порядок'),
            array('Медиа', admin_url('edit.php?category_name=media'), 'Статьи, обложки и ссылки на публикации'),
            array('Страницы', admin_url('edit.php?post_type=page'), 'Юридические документы и основные разделы'),
            array('Заказы', admin_url('admin.php?page=wc-orders'), 'Заказы WooCommerce'),
            array('Медиафайлы', admin_url('upload.php'), 'Изображения и документы'),
            array('Меню', admin_url('nav-menus.php'), 'Навигация сайта'),
        );
        echo '<div class="wrap"><h1>Контент сайта</h1><p>Основные рабочие разделы собраны в одном месте.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1100px;margin-top:24px">';
        foreach ($links as $link) {
            printf('<a href="%1$s" style="display:block;padding:20px;border:1px solid #dcdcde;border-radius:8px;background:#fff;text-decoration:none"><strong style="display:block;font-size:17px;color:#1d2327">%2$s</strong><span style="display:block;margin-top:7px;color:#646970">%3$s</span></a>', esc_url($link[1]), esc_html($link[0]), esc_html($link[2]));
        }
        echo '</div></div>';
    }

    public static function register_dashboard_widget(): void {
        wp_add_dashboard_widget('theobroma_quick_content', 'Theobroma — быстрый доступ', array(self::class, 'render_dashboard_widget'));
    }

    public static function render_dashboard_widget(): void {
        $product_count = wp_count_posts('product');
        $media_count = wp_count_posts('post');
        printf('<p><strong>%d</strong> товаров · <strong>%d</strong> материалов</p>', (int) ($product_count->publish ?? 0), (int) ($media_count->publish ?? 0));
        printf('<p><a class="button button-primary" href="%s">Открыть контент сайта</a> <a class="button" href="%s" target="_blank" rel="noopener">Открыть сайт</a></p>', esc_url(admin_url('admin.php?page=theobroma-content')), esc_url(home_url('/')));
    }

    public static function register_product_box(): void {
        add_meta_box('theobroma_product_content', 'Theobroma — содержимое карточки', array(self::class, 'render_product_box'), 'product', 'normal', 'high');
    }

    public static function render_product_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $detail_image_id = absint(get_post_meta($post->ID, '_theobroma_product_detail_image_id', true));
        $copy = get_post_meta($post->ID, '_theobroma_detail_copy', true);
        $copy_text = is_array($copy) ? implode("\n\n", $copy) : '';
        $details = (string) get_post_meta($post->ID, '_theobroma_product_details', true);
        $benefit = (string) get_post_meta($post->ID, '_theobroma_product_benefit', true);
        $marketplaces = get_post_meta($post->ID, '_theobroma_marketplaces', true);
        $marketplaces = is_array($marketplaces) ? $marketplaces : array();
        self::media_field('theobroma_product_detail_image_id', 'Изображение в детальной карточке', $detail_image_id);
        echo '<p class="description">Рекомендуемый размер: 560 × 745 px. Изображение товара WooCommerce продолжит использоваться в каталоге.</p>';
        self::textarea('theobroma_detail_copy', 'Описание рядом с товаром', $copy_text, 'Каждый абзац отделяйте пустой строкой.');
        self::textarea('theobroma_product_details', 'Состав и характеристики', $details, 'Допускается безопасная HTML-разметка.');
        self::textarea('theobroma_product_benefit', 'Польза кокосового сахара', $benefit, 'Содержимое второго раскрывающегося блока.');
        self::input('theobroma_wb_url', 'Ссылка Wildberries', (string) ($marketplaces['wb'] ?? ''), 'url');
        self::input('theobroma_ozon_url', 'Ссылка Ozon', (string) ($marketplaces['ozon'] ?? ''), 'url');
    }

    public static function register_media_box(WP_Post $post): void {
        if (!has_category('media', $post)) {
            return;
        }
        add_meta_box('theobroma_media_content', 'Theobroma — публикация', array(self::class, 'render_media_box'), 'post', 'side', 'high');
    }

    public static function render_media_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        self::input('theobroma_article_link', 'Ссылка «Читать статью»', (string) get_post_meta($post->ID, '_theobroma_article_link', true), 'url');
        echo '<p>Изображение карточки и статьи задаётся через «Изображение записи».</p>';
    }

    public static function register_recipe_box(): void {
        add_meta_box('theobroma_recipe_content', 'Theobroma — рецепт', array(self::class, 'render_recipe_box'), 'theobroma_recipe', 'normal', 'high');
    }

    public static function enqueue_recipe_assets(string $hook): void {
        $screen = get_current_screen();
        if (!in_array($hook, array('post.php', 'post-new.php'), true) || !$screen || !in_array($screen->post_type, array('theobroma_recipe', 'product'), true)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'theobroma-recipe-admin',
            plugin_dir_url(__FILE__) . 'assets/recipe-admin.js',
            array('jquery'),
            (string) filemtime(plugin_dir_path(__FILE__) . 'assets/recipe-admin.js'),
            true
        );
        wp_enqueue_style(
            'theobroma-recipe-admin',
            plugin_dir_url(__FILE__) . 'assets/recipe-admin.css',
            array(),
            (string) filemtime(plugin_dir_path(__FILE__) . 'assets/recipe-admin.css')
        );
    }

    public static function render_recipe_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $ingredients = self::decode_rows($post->ID, '_theobroma_ingredients');
        $steps = self::decode_rows($post->ID, '_theobroma_steps');
        $card_image_id = absint(get_post_meta($post->ID, '_theobroma_card_image_id', true));
        $detail_image_id = absint(get_post_meta($post->ID, '_theobroma_detail_image_id', true));
        $product_ids = array_filter(array_map('absint', (array) get_post_meta($post->ID, '_theobroma_product_ids', true)));

        echo '<div class="theobroma-recipe-editor">';
        echo '<div class="theobroma-fields-grid">';
        self::input('theobroma_accent', 'Акцентная часть заголовка', (string) get_post_meta($post->ID, '_theobroma_accent', true), 'text');
        self::input('theobroma_heading', 'Остальная часть заголовка', (string) get_post_meta($post->ID, '_theobroma_heading', true), 'text');
        self::input('theobroma_card_title', 'Заголовок карточки', (string) get_post_meta($post->ID, '_theobroma_card_title', true), 'text');
        self::input('theobroma_cooking_time', 'Время приготовления', (string) get_post_meta($post->ID, '_theobroma_cooking_time', true), 'text');
        echo '</div>';
        echo '<p class="description">Краткое описание карточки задаётся в поле «Отрывок» выше. Порядок рецептов — в поле «Порядок» справа.</p>';
        self::media_field('theobroma_card_image_id', 'Фото карточки в списке рецептов', $card_image_id);
        self::media_field('theobroma_detail_image_id', 'Фото в инструкции', $detail_image_id);

        self::render_repeater('theobroma_ingredients', 'Ингредиенты', $ingredients, array('name' => 'Ингредиент', 'amount' => 'Количество'));
        self::render_repeater('theobroma_steps', 'Шаги приготовления', $steps, array('text' => 'Описание шага'));

        echo '<section class="theobroma-related-products"><h3>Связанные товары</h3><p class="description">Выберите до трёх товаров, которые будут показаны под рецептом.</p>';
        $products = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        for ($slot = 0; $slot < 3; $slot++) {
            printf('<label>Товар %1$d<select name="theobroma_product_ids[]"><option value="">— не выбран —</option>', $slot + 1);
            foreach ($products as $product) {
                printf('<option value="%1$d"%2$s>%3$s</option>', $product->ID, selected($product_ids[$slot] ?? 0, $product->ID, false), esc_html($product->post_title));
            }
            echo '</select></label>';
        }
        echo '</section></div>';
    }

    private static function render_repeater(string $name, string $title, array $rows, array $columns): void {
        echo '<section class="theobroma-repeater" data-repeater="' . esc_attr($name) . '"><h3>' . esc_html($title) . '</h3><div class="theobroma-repeater-rows">';
        foreach ($rows as $index => $row) {
            self::render_repeater_row($name, (int) $index, $row, $columns);
        }
        echo '</div><button type="button" class="button theobroma-add-row">Добавить строку</button>';
        echo '<template class="theobroma-row-template">';
        self::render_repeater_row($name, '__INDEX__', array(), $columns);
        echo '</template></section>';
    }

    private static function render_repeater_row(string $name, $index, array $row, array $columns): void {
        echo '<div class="theobroma-repeater-row">';
        foreach ($columns as $key => $label) {
            printf('<label><span>%1$s</span><input type="text" name="%2$s[%3$s][%4$s]" value="%5$s"></label>', esc_html($label), esc_attr($name), esc_attr((string) $index), esc_attr($key), esc_attr((string) ($row[$key] ?? '')));
        }
        echo '<button type="button" class="button-link-delete theobroma-remove-row">Удалить</button></div>';
    }

    private static function media_field(string $name, string $label, int $attachment_id): void {
        $preview = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
        printf('<section class="theobroma-media-field"><h3>%1$s</h3><input type="hidden" name="%2$s" value="%3$d"><img src="%4$s" alt=""%5$s><p><button type="button" class="button theobroma-select-media">Выбрать изображение</button> <button type="button" class="button-link-delete theobroma-remove-media"%6$s>Удалить</button></p></section>', esc_html($label), esc_attr($name), $attachment_id, esc_url($preview), $preview ? '' : ' hidden', $preview ? '' : ' hidden');
    }

    public static function save_product_fields(int $post_id): void {
        if (!self::can_save($post_id)) {
            return;
        }
        update_post_meta($post_id, '_theobroma_product_detail_image_id', absint($_POST['theobroma_product_detail_image_id'] ?? 0));
        $copy_raw = isset($_POST['theobroma_detail_copy']) ? sanitize_textarea_field(wp_unslash($_POST['theobroma_detail_copy'])) : '';
        $copy = array_values(array_filter(array_map('trim', preg_split('~\R\s*\R~u', $copy_raw) ?: array())));
        update_post_meta($post_id, '_theobroma_detail_copy', $copy);
        update_post_meta($post_id, '_theobroma_product_details', wp_kses_post(wp_unslash($_POST['theobroma_product_details'] ?? '')));
        update_post_meta($post_id, '_theobroma_product_benefit', wp_kses_post(wp_unslash($_POST['theobroma_product_benefit'] ?? '')));
        update_post_meta($post_id, '_theobroma_marketplaces', array(
            'wb' => esc_url_raw(wp_unslash($_POST['theobroma_wb_url'] ?? '')),
            'ozon' => esc_url_raw(wp_unslash($_POST['theobroma_ozon_url'] ?? '')),
        ));
    }

    public static function save_media_fields(int $post_id): void {
        if (!self::can_save($post_id) || !has_category('media', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_theobroma_article_link', esc_url_raw(wp_unslash($_POST['theobroma_article_link'] ?? '')));
    }

    public static function save_recipe_fields(int $post_id): void {
        if (!self::can_save($post_id)) {
            return;
        }
        foreach (array('accent', 'heading', 'card_title', 'cooking_time') as $field) {
            update_post_meta($post_id, '_theobroma_' . $field, sanitize_text_field(wp_unslash($_POST['theobroma_' . $field] ?? '')));
        }
        foreach (array('card_image_id', 'detail_image_id') as $field) {
            update_post_meta($post_id, '_theobroma_' . $field, absint($_POST['theobroma_' . $field] ?? 0));
        }
        update_post_meta($post_id, '_theobroma_ingredients', wp_json_encode(self::sanitize_rows($_POST['theobroma_ingredients'] ?? array(), array('name', 'amount')), JSON_UNESCAPED_UNICODE));
        update_post_meta($post_id, '_theobroma_steps', wp_json_encode(self::sanitize_rows($_POST['theobroma_steps'] ?? array(), array('text')), JSON_UNESCAPED_UNICODE));
        $product_ids = array_slice(array_values(array_filter(array_map('absint', (array) ($_POST['theobroma_product_ids'] ?? array())))), 0, 3);
        update_post_meta($post_id, '_theobroma_product_ids', $product_ids);
    }

    private static function decode_rows(int $post_id, string $key): array {
        $value = get_post_meta($post_id, $key, true);
        $rows = is_string($value) ? json_decode($value, true) : array();
        return is_array($rows) ? $rows : array();
    }

    private static function sanitize_rows($rows, array $keys): array {
        if (!is_array($rows)) {
            return array();
        }
        $clean = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = array();
            foreach ($keys as $key) {
                $item[$key] = sanitize_textarea_field(wp_unslash($row[$key] ?? ''));
            }
            if (array_filter($item, static fn(string $value): bool => $value !== '')) {
                $clean[] = $item;
            }
        }
        return $clean;
    }

    private static function can_save(int $post_id): bool {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return false;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        return current_user_can('edit_post', $post_id);
    }

    public static function add_product_columns(array $columns): array {
        $columns['theobroma_sku'] = 'Артикул';
        $columns['theobroma_source'] = 'Данные оригинала';
        return $columns;
    }

    public static function render_product_column(string $column, int $post_id): void {
        $product = wc_get_product($post_id);
        if (!$product instanceof WC_Product) {
            return;
        }
        if ($column === 'theobroma_sku') {
            echo esc_html($product->get_sku());
        }
        if ($column === 'theobroma_source') {
            echo get_post_meta($post_id, '_theobroma_source_url', true) ? '<span style="color:#008a20">Заполнены</span>' : '<span style="color:#b32d2e">Нет</span>';
        }
    }

    private static function textarea(string $name, string $label, string $value, string $help): void {
        printf('<p><label for="%1$s"><strong>%2$s</strong></label><br><textarea class="widefat" rows="6" id="%1$s" name="%1$s">%3$s</textarea><span class="description">%4$s</span></p>', esc_attr($name), esc_html($label), esc_textarea($value), esc_html($help));
    }

    private static function input(string $name, string $label, string $value, string $type): void {
        printf('<p><label for="%1$s"><strong>%2$s</strong></label><br><input class="widefat" type="%4$s" id="%1$s" name="%1$s" value="%3$s"></p>', esc_attr($name), esc_html($label), esc_attr($value), esc_attr($type));
    }
}

Theobroma_Admin_Tools::boot();

<?php
declare(strict_types=1);

/**
 * Content types and editor helpers for the "Where to buy" page.
 */

function theobroma_buy_post_types(): array {
    return array('theobroma_boutique', 'theobroma_partner');
}

function theobroma_buy_meta_schema(string $post_type): array {
    if ($post_type === 'theobroma_boutique') {
        return array(
            'image_id' => array('label' => 'Фотография', 'type' => 'image'),
            'address' => array('label' => 'Адрес', 'type' => 'textarea'),
            'hours' => array('label' => 'Часы работы', 'type' => 'text'),
            'map_url' => array('label' => 'Ссылка «Как добраться»', 'type' => 'url'),
        );
    }

    if ($post_type === 'theobroma_partner') {
        return array(
            'image_id' => array('label' => 'Логотип', 'type' => 'image'),
            'city' => array('label' => 'Город', 'type' => 'text'),
            'store_url' => array('label' => 'Ссылка на магазин или сайт', 'type' => 'url'),
        );
    }

    return array();
}

function theobroma_buy_meta_key(string $key): string {
    return '_theobroma_buy_' . sanitize_key($key);
}

function theobroma_buy_meta(string $key, int $post_id): string {
    return (string) get_post_meta($post_id, theobroma_buy_meta_key($key), true);
}

function theobroma_buy_image_url(int $post_id, string $size = 'full'): string {
    $image_id = absint(theobroma_buy_meta('image_id', $post_id));
    if ($image_id > 0) {
        $attachment_url = wp_get_attachment_image_url($image_id, $size);
        if (is_string($attachment_url) && $attachment_url !== '') {
            return $attachment_url;
        }
    }

    // Legacy records keep the original theme asset until an editor selects a media item.
    return theobroma_buy_meta('image_url', $post_id) ?: theobroma_buy_meta('source_image_url', $post_id);
}

/** @return WP_Post[] */
function theobroma_buy_get_entries(string $post_type): array {
    if (!in_array($post_type, theobroma_buy_post_types(), true)) {
        return array();
    }

    return array_values(array_filter(get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
        'order' => 'ASC',
    )), static fn($post): bool => $post instanceof WP_Post));
}

function theobroma_buy_register_content_types(): void {
    $common = array(
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => false,
        'show_in_menu' => 'theobroma-buy',
        'capability_type' => 'page',
        'map_meta_cap' => true,
        'supports' => array('title', 'page-attributes'),
        'menu_position' => 5,
        'has_archive' => false,
        'rewrite' => false,
    );

    register_post_type('theobroma_boutique', array_merge($common, array(
        'labels' => array(
            'name' => 'Бутики',
            'singular_name' => 'Бутик',
            'menu_name' => 'Бутики',
            'add_new' => 'Добавить бутик',
            'add_new_item' => 'Добавить бутик',
            'edit_item' => 'Редактировать бутик',
            'new_item' => 'Новый бутик',
            'view_item' => 'Посмотреть бутик',
            'search_items' => 'Найти бутик',
            'not_found' => 'Бутики не найдены',
        ),
    )));

    register_post_type('theobroma_partner', array_merge($common, array(
        'labels' => array(
            'name' => 'Партнёры',
            'singular_name' => 'Партнёр',
            'menu_name' => 'Партнёры',
            'add_new' => 'Добавить партнёра',
            'add_new_item' => 'Добавить партнёра',
            'edit_item' => 'Редактировать партнёра',
            'new_item' => 'Новый партнёр',
            'view_item' => 'Посмотреть партнёра',
            'search_items' => 'Найти партнёра',
            'not_found' => 'Партнёры не найдены',
        ),
    )));
}
add_action('init', 'theobroma_buy_register_content_types');

function theobroma_buy_register_meta(): void {
    foreach (theobroma_buy_post_types() as $post_type) {
        foreach (theobroma_buy_meta_schema($post_type) as $key => $field) {
            register_post_meta($post_type, theobroma_buy_meta_key($key), array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => static fn($value): string => $field['type'] === 'url'
                    ? theobroma_buy_sanitize_url($value)
                    : sanitize_textarea_field((string) $value),
                'auth_callback' => static fn(): bool => current_user_can('edit_pages'),
            ));
        }
    }
}
add_action('init', 'theobroma_buy_register_meta', 20);

function theobroma_buy_admin_menu(): void {
    add_menu_page(
        'Где купить',
        'Где купить',
        'edit_pages',
        'theobroma-buy',
        'theobroma_buy_render_admin_dashboard',
        'dashicons-store',
        58
    );
}
add_action('admin_menu', 'theobroma_buy_admin_menu');

function theobroma_buy_render_admin_dashboard(): void {
    $boutiques_url = admin_url('edit.php?post_type=theobroma_boutique');
    $partners_url = admin_url('edit.php?post_type=theobroma_partner');
    ?>
    <div class="wrap theobroma-buy-admin">
        <div class="theobroma-buy-admin-hero">
            <span class="theobroma-buy-admin-kicker">THEOBROMA</span>
            <h1>Где купить</h1>
            <p>Управляйте бутиками и партнёрами, которые видят покупатели на странице доставки.</p>
        </div>
        <div class="theobroma-buy-admin-grid">
            <a class="theobroma-buy-admin-card" href="<?php echo esc_url($boutiques_url); ?>">
                <span class="dashicons dashicons-store" aria-hidden="true"></span>
                <span><strong>Бутики</strong><small>Фотографии, адреса и часы работы</small></span>
                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
            </a>
            <a class="theobroma-buy-admin-card" href="<?php echo esc_url($partners_url); ?>">
                <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                <span><strong>Партнёры</strong><small>Логотипы, города и ссылки на магазины</small></span>
                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
            </a>
        </div>
    </div>
    <?php
}

function theobroma_buy_add_meta_boxes(): void {
    add_meta_box(
        'theobroma-boutique-details',
        'Данные бутика',
        'theobroma_buy_render_boutique_box',
        'theobroma_boutique',
        'normal',
        'high'
    );
    add_meta_box(
        'theobroma-partner-details',
        'Данные партнёра',
        'theobroma_buy_render_partner_box',
        'theobroma_partner',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'theobroma_buy_add_meta_boxes');

function theobroma_buy_render_image_field(int $post_id, string $label, string $input_key, string $fallback_url = ''): void {
    $image_id = absint(theobroma_buy_meta('image_id', $post_id));
    $image_url = theobroma_buy_image_url($post_id, 'medium') ?: $fallback_url;
    ?>
    <div class="theobroma-buy-image-field" data-image-field>
        <span class="theobroma-buy-field-label"><?php echo esc_html($label); ?></span>
        <input type="hidden" name="theobroma_buy_image_id" value="<?php echo esc_attr((string) $image_id); ?>" data-image-id>
        <input type="hidden" name="theobroma_buy_image_clear" value="0" data-image-clear>
        <div class="theobroma-buy-image-preview<?php echo $image_url !== '' ? ' has-image' : ''; ?>" data-image-preview>
            <?php if ($image_url !== '') : ?><img src="<?php echo esc_url($image_url); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php endif; ?>
        </div>
        <div class="theobroma-buy-image-actions">
            <button type="button" class="button button-secondary theobroma-buy-image-select" data-image-select>Выбрать изображение</button>
            <button type="button" class="button-link-delete theobroma-buy-image-remove" data-image-remove<?php echo $image_url === '' ? ' hidden' : ''; ?>>Убрать</button>
        </div>
        <p class="description">Рекомендуемый формат — горизонтальное изображение без мелкого текста.</p>
    </div>
    <?php
}

function theobroma_buy_render_boutique_box(WP_Post $post): void {
    wp_nonce_field('theobroma_buy_boutique_save', 'theobroma_buy_boutique_nonce');
    ?>
    <div class="theobroma-buy-editor">
        <div class="theobroma-buy-editor-main">
            <?php theobroma_buy_render_image_field($post->ID, 'Фотография бутика', 'image_id'); ?>
            <div class="theobroma-buy-field">
                <label for="theobroma-buy-address">Адрес</label>
                <textarea id="theobroma-buy-address" name="theobroma_buy_address" rows="3" placeholder="Москва, ТЦ «Авиапарк»"><?php echo esc_textarea(theobroma_buy_meta('address', $post->ID)); ?></textarea>
            </div>
        </div>
        <div class="theobroma-buy-editor-side">
            <div class="theobroma-buy-field">
                <label for="theobroma-buy-hours">Часы работы</label>
                <input id="theobroma-buy-hours" type="text" name="theobroma_buy_hours" value="<?php echo esc_attr(theobroma_buy_meta('hours', $post->ID)); ?>" placeholder="Ежедневно 10:00–22:00">
            </div>
            <div class="theobroma-buy-field">
                <label for="theobroma-buy-map-url">Ссылка «Как добраться»</label>
                <input id="theobroma-buy-map-url" type="url" name="theobroma_buy_map_url" value="<?php echo esc_attr(theobroma_buy_meta('map_url', $post->ID)); ?>" placeholder="https://yandex.ru/maps/...">
            </div>
            <?php theobroma_buy_render_order_field($post); ?>
        </div>
    </div>
    <?php
}

function theobroma_buy_render_partner_box(WP_Post $post): void {
    wp_nonce_field('theobroma_buy_partner_save', 'theobroma_buy_partner_nonce');
    ?>
    <div class="theobroma-buy-editor">
        <div class="theobroma-buy-editor-main">
            <?php theobroma_buy_render_image_field($post->ID, 'Логотип партнёра', 'image_id'); ?>
        </div>
        <div class="theobroma-buy-editor-side">
            <div class="theobroma-buy-field">
                <label for="theobroma-buy-city">Город</label>
                <input id="theobroma-buy-city" type="text" name="theobroma_buy_city" value="<?php echo esc_attr(theobroma_buy_meta('city', $post->ID)); ?>" placeholder="Москва">
            </div>
            <div class="theobroma-buy-field">
                <label for="theobroma-buy-store-url">Ссылка на магазин или сайт</label>
                <input id="theobroma-buy-store-url" type="url" name="theobroma_buy_store_url" value="<?php echo esc_attr(theobroma_buy_meta('store_url', $post->ID)); ?>" placeholder="https://example.ru">
            </div>
            <?php theobroma_buy_render_order_field($post); ?>
        </div>
    </div>
    <?php
}

function theobroma_buy_render_order_field(WP_Post $post): void {
    ?>
    <div class="theobroma-buy-field theobroma-buy-order-field">
        <label for="theobroma-buy-menu-order">Порядок вывода</label>
        <input id="theobroma-buy-menu-order" type="number" min="0" step="1" name="theobroma_buy_menu_order" value="<?php echo esc_attr((string) $post->menu_order); ?>">
        <p class="description">Меньшее число поднимает карточку выше.</p>
    </div>
    <?php
}

function theobroma_buy_sanitize_url(mixed $value): string {
    $raw_url = is_scalar($value) ? trim((string) $value) : '';
    $url = esc_url_raw($raw_url);
    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, array('http', 'https'), true) ? $url : '';
}

function theobroma_buy_save_post(int $post_id, WP_Post $post): void {
    if (!in_array($post->post_type, theobroma_buy_post_types(), true) || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    $nonce_action = $post->post_type === 'theobroma_boutique' ? 'theobroma_buy_boutique_save' : 'theobroma_buy_partner_save';
    $nonce_name = $post->post_type === 'theobroma_boutique' ? 'theobroma_buy_boutique_nonce' : 'theobroma_buy_partner_nonce';
    $raw_nonce = isset($_POST[$nonce_name]) && is_scalar($_POST[$nonce_name]) ? wp_unslash($_POST[$nonce_name]) : '';
    if ($raw_nonce === '' || !wp_verify_nonce(sanitize_text_field($raw_nonce), $nonce_action) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $schema = theobroma_buy_meta_schema($post->post_type);
    foreach ($schema as $key => $field) {
        $form_key = 'theobroma_buy_' . $key;
        if (!array_key_exists($form_key, $_POST)) {
            continue;
        }
        $raw_value = is_scalar($_POST[$form_key]) ? wp_unslash($_POST[$form_key]) : '';
        if ($field['type'] === 'image') {
            $image_value = absint($raw_value);
            if ($image_value === 0) {
                if (!empty($_POST['theobroma_buy_image_clear'])) {
                    delete_post_meta($post_id, theobroma_buy_meta_key($key));
                    delete_post_meta($post_id, theobroma_buy_meta_key('image_url'));
                    delete_post_meta($post_id, theobroma_buy_meta_key('source_image_url'));
                }
                continue;
            }
            $value = (string) $image_value;
        } elseif ($field['type'] === 'url') {
            $value = theobroma_buy_sanitize_url($raw_value);
        } elseif ($field['type'] === 'textarea') {
            $value = sanitize_textarea_field((string) $raw_value);
        } else {
            $value = sanitize_text_field((string) $raw_value);
        }
        if ($value === '') {
            delete_post_meta($post_id, theobroma_buy_meta_key($key));
        } else {
            update_post_meta($post_id, theobroma_buy_meta_key($key), $value);
        }
        if ($field['type'] === 'image') {
            delete_post_meta($post_id, theobroma_buy_meta_key('image_url'));
            delete_post_meta($post_id, theobroma_buy_meta_key('source_image_url'));
        }
    }

    $order = isset($_POST['theobroma_buy_menu_order']) ? max(0, absint(wp_unslash($_POST['theobroma_buy_menu_order']))) : $post->menu_order;
    if ($order !== $post->menu_order) {
        remove_action('save_post', 'theobroma_buy_save_post', 10);
        wp_update_post(array('ID' => $post_id, 'menu_order' => $order));
        add_action('save_post', 'theobroma_buy_save_post', 10, 3);
    }
}
add_action('save_post', 'theobroma_buy_save_post', 10, 3);

function theobroma_buy_admin_columns(array $columns): array {
    return array(
        'cb' => $columns['cb'] ?? '<input type="checkbox">',
        'buy_preview' => 'Превью',
        'title' => $columns['title'] ?? 'Название',
        'buy_location' => 'Город / адрес',
        'buy_order' => 'Порядок',
        'date' => $columns['date'] ?? 'Дата',
    );
}
add_filter('manage_theobroma_boutique_posts_columns', 'theobroma_buy_admin_columns');
add_filter('manage_theobroma_partner_posts_columns', 'theobroma_buy_admin_columns');

function theobroma_buy_admin_column(string $column, int $post_id): void {
    if ($column === 'buy_preview') {
        $image_id = absint(theobroma_buy_meta('image_id', $post_id));
        $image_url = theobroma_buy_image_url($post_id, 'thumbnail');
        echo $image_id > 0 ? wp_get_attachment_image($image_id, array(64, 48), true, array('class' => 'theobroma-buy-list-thumb')) : ($image_url !== '' ? '<img class="theobroma-buy-list-thumb" src="' . esc_url($image_url) . '" alt="">' : '<span class="theobroma-buy-list-placeholder">—</span>');
    } elseif ($column === 'buy_location') {
        $post_type = get_post_type($post_id);
        echo esc_html($post_type === 'theobroma_boutique' ? theobroma_buy_meta('address', $post_id) : theobroma_buy_meta('city', $post_id));
    } elseif ($column === 'buy_order') {
        echo esc_html((string) get_post_field('menu_order', $post_id));
    }
}
add_action('manage_theobroma_boutique_posts_custom_column', 'theobroma_buy_admin_column', 10, 2);
add_action('manage_theobroma_partner_posts_custom_column', 'theobroma_buy_admin_column', 10, 2);

function theobroma_buy_admin_assets(string $hook): void {
    $screen = get_current_screen();
    $post_types = theobroma_buy_post_types();
    $is_buy_screen = $hook === 'toplevel_page_theobroma-buy' || ($screen instanceof WP_Screen && in_array($screen->post_type, $post_types, true));
    if (!$is_buy_screen) {
        return;
    }

    $theme_dir = get_template_directory();
    wp_enqueue_style('theobroma-buy-admin', get_template_directory_uri() . '/assets/css/buy-admin.css', array(), (string) filemtime($theme_dir . '/assets/css/buy-admin.css'));
    if ($screen instanceof WP_Screen && in_array($screen->post_type, $post_types, true)) {
        wp_enqueue_media();
        wp_enqueue_script('theobroma-buy-admin', get_template_directory_uri() . '/assets/js/buy-admin.js', array('jquery'), (string) filemtime($theme_dir . '/assets/js/buy-admin.js'), true);
        wp_localize_script('theobroma-buy-admin', 'theobromaBuyAdmin', array(
            'title' => 'Выберите изображение',
            'button' => 'Использовать изображение',
        ));
    }
}
add_action('admin_enqueue_scripts', 'theobroma_buy_admin_assets');

function theobroma_buy_migration_image_url(string $filename): string {
    return get_template_directory_uri() . '/assets/images/' . ltrim($filename, '/');
}

function theobroma_buy_migration_create(string $post_type, string $source_key, string $title, array $meta): void {
    $existing = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'meta_key' => theobroma_buy_meta_key('source_key'),
        'meta_value' => $source_key,
        'fields' => 'ids',
    ));
    if ($existing !== array()) {
        return;
    }

    $post_id = wp_insert_post(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'post_title' => $title,
        'menu_order' => (int) ($meta['menu_order'] ?? 0),
    ), true);
    if (is_wp_error($post_id)) {
        return;
    }
    update_post_meta($post_id, theobroma_buy_meta_key('source_key'), $source_key);
    foreach ($meta as $key => $value) {
        if ($key === 'menu_order' || $value === '') {
            continue;
        }
        $sanitized = in_array($key, array('map_url', 'store_url'), true)
            ? theobroma_buy_sanitize_url($value)
            : sanitize_text_field((string) $value);
        update_post_meta($post_id, theobroma_buy_meta_key($key), $sanitized);
    }
}

function theobroma_buy_migrate_entries(): void {
    if (!current_user_can('manage_options') || get_option('theobroma_buy_content_migrated_v1', false)) {
        return;
    }

    theobroma_buy_migration_create('theobroma_boutique', 'legacy-aviapark', 'ТЦ «Авиапарк»', array(
        'image_url' => theobroma_buy_migration_image_url('buy-aviapark.jpg'),
        'address' => 'Москва, ТЦ «Авиапарк»',
        'hours' => 'Ежедневно 10:00–22:00',
        'map_url' => 'https://yandex.ru/maps/?text=%D0%A2%D0%A6%20%D0%90%D0%B2%D0%B8%D0%B0%D0%BF%D0%B0%D1%80%D0%BA',
        'menu_order' => 0,
    ));

    $partners = array(
        array('ashanti.png', 'Ashanti', 'Москва'),
        array('jagannath.png', 'Джаганнат', 'Москва'),
        array('white-clouds.png', 'Белые облака', 'Москва'),
        array('vidzhai.png', 'Виджай', 'Москва'),
        array('green-cardamon.png', 'Green Cardamon', 'Москва'),
        array('sattva.png', 'Sattva', 'Москва'),
        array('delikateska.png', 'Деликатеска', 'Москва'),
        array('naturalista.png', 'Naturalista', 'Самара'),
        array('ukrop.png', 'Укроп', 'Челябинск'),
        array('kunzhut.png', 'Кунжут', 'Челябинск'),
        array('mishkin-gostinets.png', 'Мишкин гостинец', 'Нижний Тагил'),
    );
    foreach ($partners as $index => $partner) {
        theobroma_buy_migration_create('theobroma_partner', 'legacy-' . sanitize_title($partner[1]), $partner[1], array(
            'image_url' => theobroma_buy_migration_image_url('partners/' . $partner[0]),
            'city' => $partner[2],
            'menu_order' => $index,
        ));
    }

    update_option('theobroma_buy_content_migrated_v1', current_time('mysql'));
}
add_action('admin_init', 'theobroma_buy_migrate_entries', 20);

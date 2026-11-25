<?php
/** Administration uses the Settings API for permissions, nonces and validation. */
add_action('admin_menu', function () {
    add_menu_page('Видео и сторис Theobroma', 'Видео и сторис', 'manage_options', 'theobroma-videos', 'theobroma_videos_admin', 'dashicons-video-alt3', 24);
});
add_action('admin_init', function () {
    register_setting('theobroma_videos_group', 'theobroma_videos', [
        'type' => 'array',
        'sanitize_callback' => 'theobroma_videos_sanitize',
        'default' => theobroma_videos_defaults(),
    ]);
});
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_theobroma-videos') {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('theobroma-videos-admin', plugins_url('assets/admin.css', __FILE__), [], filemtime(__DIR__ . '/assets/admin.css'));
    wp_enqueue_script('theobroma-videos-admin', plugins_url('assets/admin.js', __FILE__), [], filemtime(__DIR__ . '/assets/admin.js'), true);
});

function theobroma_videos_admin_item(string $kind, $index, array $item): void {
    $item = array_replace([
        'enabled' => true, 'title' => '', 'description' => '', 'video' => '', 'poster_id' => 0,
        'button_enabled' => false, 'button_text' => 'Посмотреть шоколад', 'button_url' => '',
    ], $item);
    $prefix = 'theobroma_videos[' . $kind . '][items][' . $index . ']';
    $image = $item['poster_id'] ? wp_get_attachment_image_url($item['poster_id'], 'medium') : '';
    ?>
    <article class="tv-admin__item" data-video-item>
        <header><h3 data-item-heading><?php echo esc_html($item['title'] ?: 'Новая запись'); ?></h3>
            <div class="tv-admin__item-actions">
                <button type="button" class="button" data-item-move="-1" aria-label="Переместить выше">↑</button>
                <button type="button" class="button" data-item-move="1" aria-label="Переместить ниже">↓</button>
                <button type="button" class="button-link-delete" data-item-remove>Удалить</button>
            </div>
        </header>
        <label class="tv-admin__toggle"><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1" <?php checked($item['enabled']); ?>> Показывать запись</label>
        <div class="tv-admin__fields">
            <div class="tv-admin__poster" data-poster-field>
                <img data-poster-preview src="<?php echo esc_url($image ?: ''); ?>" alt="Обложка" <?php echo $image ? '' : 'hidden'; ?>>
                <input type="hidden" data-poster-id name="<?php echo esc_attr($prefix); ?>[poster_id]" value="<?php echo (int) $item['poster_id']; ?>">
                <button type="button" class="button" data-select-poster>Выбрать обложку</button>
                <button type="button" class="button-link" data-clear-poster>Убрать обложку</button>
            </div>
            <div class="tv-admin__text-fields">
                <label>Название<input type="text" data-item-title name="<?php echo esc_attr($prefix); ?>[title]" value="<?php echo esc_attr($item['title']); ?>"></label>
                <label>Подпись<textarea rows="2" name="<?php echo esc_attr($prefix); ?>[description]"><?php echo esc_textarea($item['description']); ?></textarea></label>
                <label>Ссылка на видео MP4 или WebM<input type="url" data-video-url name="<?php echo esc_attr($prefix); ?>[video]" value="<?php echo esc_attr($item['video']); ?>" placeholder="https://…"></label>
                <button type="button" class="button" data-select-video>Выбрать / загрузить видео</button>
            </div>
        </div>
        <fieldset class="tv-admin__button-settings">
            <legend>Кнопка в проигрывателе</legend>
            <label class="tv-admin__toggle"><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[button_enabled]" value="1" <?php checked($item['button_enabled']); ?>> Показывать кнопку</label>
            <label>Текст кнопки<input type="text" name="<?php echo esc_attr($prefix); ?>[button_text]" value="<?php echo esc_attr($item['button_text']); ?>"></label>
            <label>Ссылка кнопки<input type="text" name="<?php echo esc_attr($prefix); ?>[button_url]" value="<?php echo esc_attr($item['button_url']); ?>" placeholder="/catalog/ или https://…"></label>
            <p class="description">Кнопка появится, когда включён показ и заполнены текст и ссылка.</p>
        </fieldset>
    </article>
    <?php
}

function theobroma_videos_admin(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.');
    }
    $settings = theobroma_videos_settings();
    $labels = ['stories' => 'Сторис · кружочки', 'reels' => 'Ролики · карточки'];
    ?>
    <div class="wrap tv-admin" data-video-admin>
        <header class="tv-admin__hero"><h1>Видео и сторис</h1><p>Живые истории Theobroma. Соберите ленты и настройте каждый ролик.</p></header>
        <?php settings_errors('theobroma_videos'); ?>
        <nav class="tv-admin__tabs" aria-label="Настройка видеоблоков">
            <?php foreach ($labels as $kind => $label) : ?>
                <button type="button" data-admin-tab="<?php echo esc_attr($kind); ?>" aria-pressed="<?php echo $kind === 'stories' ? 'true' : 'false'; ?>"><?php echo esc_html($label); ?></button>
            <?php endforeach; ?>
        </nav>
        <form action="options.php" method="post" data-video-form>
            <?php settings_fields('theobroma_videos_group'); ?>
            <?php foreach ($settings as $kind => $section) : $prefix = 'theobroma_videos[' . $kind . ']'; ?>
                <section data-admin-panel="<?php echo esc_attr($kind); ?>" <?php echo $kind === 'stories' ? '' : 'hidden'; ?>>
                    <div class="tv-admin__section-settings">
                        <h2><?php echo esc_html($labels[$kind]); ?></h2>
                        <p><?php echo $kind === 'stories' ? 'Круглые обложки перед каталогом на главной.' : 'Вертикальные карточки после каталога на главной.'; ?></p>
                        <label class="tv-admin__toggle"><input type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1" <?php checked($section['enabled']); ?>> Показывать блок на сайте</label>
                        <label>Заголовок блока<input type="text" name="<?php echo esc_attr($prefix); ?>[title]" value="<?php echo esc_attr($section['title']); ?>"></label>
                        <label>Описание блока<textarea rows="2" name="<?php echo esc_attr($prefix); ?>[description]"><?php echo esc_textarea($section['description']); ?></textarea></label>
                    </div>
                    <div data-video-items>
                        <?php foreach ($section['items'] as $index => $item) { theobroma_videos_admin_item($kind, $index, $item); } ?>
                    </div>
                    <button type="button" class="button button-secondary" data-item-add>Добавить <?php echo $kind === 'stories' ? 'сторис' : 'ролик'; ?></button>
                    <p class="description">До 30 записей. Для совместимости используйте MP4 (H.264). Порядок меняется стрелками. Записи без видео не показываются.</p>
                </section>
                <template data-item-template="<?php echo esc_attr($kind); ?>"><?php theobroma_videos_admin_item($kind, '__INDEX__', []); ?></template>
            <?php endforeach; ?>
            <?php submit_button('Сохранить видео и сторис'); ?>
            <p data-admin-status role="status"></p>
        </form>
    </div>
    <?php
}

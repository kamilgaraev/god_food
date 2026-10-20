<?php
declare(strict_types=1);

function theobroma_register_corporate_sections_admin(): void {
    add_submenu_page(
        class_exists('Theobroma_Admin_Tools') ? 'theobroma-content' : 'themes.php',
        'Корпоративная страница', 'Корпоративная страница', 'manage_options',
        'theobroma-corporate-sections', 'theobroma_render_corporate_sections_admin'
    );
}
add_action('admin_menu', 'theobroma_register_corporate_sections_admin', 20);

function theobroma_register_corporate_sections_setting(): void {
    register_setting('theobroma_corporate_sections', 'theobroma_corporate_sections', array(
        'type' => 'array',
        'sanitize_callback' => 'theobroma_sanitize_corporate_sections',
        'default' => theobroma_corporate_sections_defaults(),
    ));
}
add_action('admin_init', 'theobroma_register_corporate_sections_setting');

function theobroma_corporate_sections_admin_assets(string $hook): void {
    if (!str_ends_with($hook, '_page_theobroma-corporate-sections')) {
        return;
    }
    wp_enqueue_media();
    foreach (array('css', 'js') as $type) {
        $relative = '/assets/' . $type . '/admin-corporate-sections.' . $type;
        $url = get_template_directory_uri() . $relative;
        $version = (string) filemtime(get_template_directory() . $relative);
        if ($type === 'css') {
            wp_enqueue_style('theobroma-corporate-sections-admin', $url, array(), $version);
        } else {
            wp_enqueue_script('theobroma-corporate-sections-admin', $url, array('media-views'), $version, true);
        }
    }
}
add_action('admin_enqueue_scripts', 'theobroma_corporate_sections_admin_assets');

function theobroma_corporate_admin_field(string $name, string $label, string $value, bool $multiline = false): void {
    ?>
    <label class="cs-admin-field"><span><?php echo esc_html($label); ?></span>
        <?php if ($multiline) : ?><textarea name="<?php echo esc_attr($name); ?>" rows="3"><?php echo esc_textarea($value); ?></textarea>
        <?php else : ?><input type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"><?php endif; ?>
    </label>
    <?php
}

function theobroma_corporate_admin_row(string $section, string $index, array $item): void {
    $prefix = 'theobroma_corporate_sections[' . $section . '][items][' . $index . ']';
    ?>
    <fieldset class="cs-admin-row" data-cs-row tabindex="-1">
        <legend><span data-cs-number></span> <?php echo $section === 'portfolio' ? 'Пример подарка' : 'Этап заказа'; ?></legend>
        <?php if ($section === 'portfolio') : ?>
        <div class="cs-admin-media">
            <div data-cs-preview><?php echo theobroma_corporate_portfolio_image($item, true); ?></div>
            <input type="hidden" data-cs-image name="<?php echo esc_attr($prefix); ?>[attachment_id]" value="<?php echo esc_attr((string) $item['attachment_id']); ?>">
            <input type="hidden" data-cs-asset name="<?php echo esc_attr($prefix); ?>[asset]" value="<?php echo esc_attr($item['asset']); ?>">
            <button type="button" class="button" data-cs-media>Выбрать фото</button>
            <button type="button" class="button-link-delete" data-cs-clear>Убрать фото</button>
            <p data-cs-no-photo<?php echo ($item['attachment_id'] || $item['asset']) ? ' hidden' : ''; ?>>Выберите фото: без него карточка не появится на сайте.</p>
        </div>
        <?php endif; ?>
        <div class="cs-admin-copy">
            <?php theobroma_corporate_admin_field($prefix . '[title]', 'Название', $item['title']); ?>
            <?php theobroma_corporate_admin_field($prefix . '[text]', 'Описание', $item['text'], true); ?>
            <?php if ($section === 'portfolio') : ?><?php theobroma_corporate_admin_field($prefix . '[alt]', 'Описание фото для незрячих пользователей (если пусто — название)', $item['alt']); ?><?php endif; ?>
        </div>
        <div class="cs-admin-row-actions">
            <button type="button" class="button" data-cs-move="up">Выше</button>
            <button type="button" class="button" data-cs-move="down">Ниже</button>
            <button type="button" class="button-link-delete" data-cs-remove>Удалить</button>
        </div>
    </fieldset>
    <?php
}

function theobroma_render_corporate_sections_admin(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав для редактирования корпоративной страницы.');
    }
    $settings = theobroma_corporate_sections();
    ?>
    <div class="wrap cs-admin" data-cs-admin>
        <h1>Корпоративная страница</h1>
        <p>Портфолио и этапы работы на странице «Корпоративные подарки». Изменения появятся после сохранения.</p>
        <?php settings_errors(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
            <?php settings_fields('theobroma_corporate_sections'); ?>
            <?php foreach ($settings as $section => $content) : ?>
            <section class="cs-admin-section" data-cs-section="<?php echo esc_attr($section); ?>">
                <h2><?php echo $section === 'portfolio' ? 'Портфолио · примеры подарков' : 'Как мы работаем'; ?></h2>
                <input type="hidden" name="theobroma_corporate_sections[<?php echo esc_attr($section); ?>][enabled]" value="0">
                <label><input type="checkbox" name="theobroma_corporate_sections[<?php echo esc_attr($section); ?>][enabled]" value="1" <?php checked($content['enabled']); ?>> Показывать блок</label>
                <?php theobroma_corporate_admin_field('theobroma_corporate_sections[' . $section . '][title]', 'Заголовок блока', $content['title']); ?>
                <?php theobroma_corporate_admin_field('theobroma_corporate_sections[' . $section . '][title_accent]', 'Выделить золотым курсивом (точная часть заголовка)', $content['title_accent']); ?>
                <?php theobroma_corporate_admin_field('theobroma_corporate_sections[' . $section . '][intro]', 'Вступление', $content['intro'], true); ?>
                <?php if ($section === 'process') : ?>
                <details class="cs-admin-factory"><summary>Производство и сроки</summary>
                    <?php foreach (array('factory_title' => 'Заголовок о производстве', 'factory_value' => 'Объём производства — число', 'factory_unit' => 'Единица объёма', 'factory_text' => 'Описание производства', 'timing_title' => 'Заголовок заметки о сроках', 'timing_text' => 'Заметка о сроках') as $key => $label) : ?>
                    <?php theobroma_corporate_admin_field('theobroma_corporate_sections[process][' . $key . ']', $label, $content[$key], in_array($key, array('factory_text', 'timing_text'), true)); ?>
                    <?php endforeach; ?>
                </details>
                <?php else : ?><p>Начальные фото — примеры наборов Theobroma. Замените их фотографиями своих проектов и добавьте подписи. Рекомендуем фото шириной от 1200 px.</p><?php endif; ?>
                <p>До 12 карточек. Порядок меняется кнопками «Выше» и «Ниже». Карточка без названия не сохраняется.</p>
                <input type="hidden" name="theobroma_corporate_sections[<?php echo esc_attr($section); ?>][items]" value="">
                <div data-cs-list>
                    <?php foreach ($content['items'] as $index => $item) : ?><?php theobroma_corporate_admin_row($section, (string) $index, $item); ?><?php endforeach; ?>
                </div>
                <template data-cs-template><?php theobroma_corporate_admin_row($section, '__INDEX__', array('title' => '', 'text' => '', 'attachment_id' => 0, 'asset' => '', 'alt' => '')); ?></template>
                <button type="button" class="button" data-cs-add><?php echo $section === 'portfolio' ? 'Добавить пример' : 'Добавить этап'; ?></button>
                <span data-cs-status role="status" aria-live="polite"></span>
            </section>
            <?php endforeach; ?>
            <?php submit_button('Сохранить корпоративную страницу'); ?>
        </form>
    </div>
    <?php
}

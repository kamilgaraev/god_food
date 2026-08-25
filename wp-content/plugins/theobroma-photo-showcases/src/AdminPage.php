<?php

declare(strict_types=1);

namespace Theobroma\PhotoShowcases;

final class AdminPage
{
    private const PAGE = 'theobroma-photo-showcases';
    private const SETTINGS_GROUP = 'theobroma_photo_showcases_group';

    public function __construct(
        private readonly Settings $settings,
        private readonly DefaultImages $defaultImages
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'settings'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }

    public function menu(): void
    {
        add_menu_page(
            'Фотоподборки Theobroma',
            'Фотоподборки',
            'manage_options',
            self::PAGE,
            array($this, 'render'),
            'dashicons-format-gallery',
            23
        );
    }

    public function settings(): void
    {
        register_setting(self::SETTINGS_GROUP, Settings::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array($this->settings, 'sanitize'),
            'default' => $this->settings->defaults(),
        ));
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'theobroma-photo-showcases-admin',
            plugins_url('assets/admin.css', THEOBROMA_PHOTO_SHOWCASES_FILE),
            array(),
            THEOBROMA_PHOTO_SHOWCASES_VERSION
        );
        wp_enqueue_script(
            'theobroma-photo-showcases-admin',
            plugins_url('assets/admin.js', THEOBROMA_PHOTO_SHOWCASES_FILE),
            array(),
            THEOBROMA_PHOTO_SHOWCASES_VERSION,
            array('in_footer' => true)
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав для управления фотоподборками.');
        }

        $collections = $this->collections();
        ?>
        <div class="theobroma-photo-admin wrap" data-photo-admin>
            <header class="theobroma-photo-admin__hero">
                <div>
                    <span class="theobroma-photo-admin__eyebrow">THEOBROMA · VISUAL STORIES</span>
                    <h1>Фотоподборки</h1>
                    <p>Соберите живую визуальную историю для Главной и корпоративных подарков — без правки страниц и кода.</p>
                </div>
                <div class="theobroma-photo-admin__hero-mark" aria-hidden="true"><span>2</span><small>точки<br>показа</small></div>
            </header>

            <nav class="theobroma-photo-admin__tabs" role="tablist" aria-label="Точки показа фотоподборок">
                <?php foreach ($this->labels() as $location => $label) : ?>
                    <button class="theobroma-photo-admin__tab<?php echo $location === 'home' ? ' is-active' : ''; ?>" type="button" role="tab" id="photo-tab-<?php echo esc_attr($location); ?>" aria-controls="photo-panel-<?php echo esc_attr($location); ?>" aria-selected="<?php echo $location === 'home' ? 'true' : 'false'; ?>" tabindex="<?php echo $location === 'home' ? '0' : '-1'; ?>" data-showcase-tab="<?php echo esc_attr($location); ?>">
                        <span class="dashicons <?php echo $location === 'home' ? 'dashicons-admin-home' : 'dashicons-businessperson'; ?>" aria-hidden="true"></span>
                        <span><strong><?php echo esc_html($label['title']); ?></strong><small><?php echo esc_html($label['hint']); ?></small></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <form action="options.php" method="post">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <?php foreach ($collections as $location => $collection) : ?>
                    <?php $this->renderPanel($location, $collection, $location !== 'home'); ?>
                <?php endforeach; ?>
                <footer class="theobroma-photo-admin__save">
                    <span><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Изменения появятся на сайте после сохранения</span>
                    <button class="button button-primary" type="submit">Сохранить фотоподборки</button>
                </footer>
            </form>
        </div>
        <?php
    }

    /** @param array<string, mixed> $collection */
    private function renderPanel(string $location, array $collection, bool $hidden): void
    {
        $prefix = Settings::OPTION . '[' . $location . ']';
        $images = isset($collection['images']) && is_array($collection['images']) ? $collection['images'] : array();
        ?>
        <section class="theobroma-photo-admin__panel" id="photo-panel-<?php echo esc_attr($location); ?>" role="tabpanel" aria-labelledby="photo-tab-<?php echo esc_attr($location); ?>" data-showcase-panel="<?php echo esc_attr($location); ?>"<?php echo $hidden ? ' hidden' : ''; ?>>
            <div class="theobroma-photo-admin__panel-heading">
                <div><span><?php echo esc_html($location === 'home' ? 'Редакционная мозаика' : 'Галерея кейсов'); ?></span><h2><?php echo esc_html($this->labels()[$location]['title']); ?></h2></div>
                <label class="theobroma-photo-switch">
                    <input type="hidden" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="0">
                    <input class="theobroma-photo-switch__input" type="checkbox" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="1" <?php checked(!empty($collection['enabled'])); ?>>
                    <span class="theobroma-photo-switch__track" aria-hidden="true"></span><span>Показывать на сайте</span>
                </label>
            </div>

            <div class="theobroma-photo-admin__content-grid">
                <aside class="theobroma-photo-admin__copy-card">
                    <div class="theobroma-photo-admin__card-heading"><span class="dashicons dashicons-edit" aria-hidden="true"></span><div><h3>Текст блока</h3><p>Короткая подводка помогает фотографиям рассказать одну историю.</p></div></div>
                    <?php $this->textField($prefix, 'eyebrow', 'Надзаголовок', (string) ($collection['eyebrow'] ?? '')); ?>
                    <?php $this->textField($prefix, 'title', 'Заголовок', (string) ($collection['title'] ?? '')); ?>
                    <label class="theobroma-photo-field"><span>Описание</span><textarea name="<?php echo esc_attr($prefix . '[description]'); ?>" rows="5"><?php echo esc_html((string) ($collection['description'] ?? '')); ?></textarea></label>
                    <div class="theobroma-photo-admin__tip"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><p><?php echo esc_html($location === 'home' ? 'Лучше всего работают 5 кадров: один крупный и четыре детальных.' : 'Используйте 3–5 кадров с упаковкой, брендированием и готовыми наборами.'); ?></p></div>
                </aside>

                <div class="theobroma-photo-admin__gallery-card" data-photo-collection data-location="<?php echo esc_attr($location); ?>" data-max-photos="<?php echo esc_attr((string) Settings::MAX_IMAGES); ?>">
                    <div class="theobroma-photo-admin__card-heading theobroma-photo-admin__card-heading--action">
                        <div><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span><div><h3>Фотографии</h3><p>Перетаскивайте карточки или используйте стрелки. Максимум 8 кадров.</p></div></div>
                        <button class="button theobroma-photo-admin__media-button" type="button" data-open-media><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>Добавить фотографии</button>
                    </div>
                    <div class="theobroma-photo-admin__photo-list" data-photo-list>
                        <?php foreach (array_values($images) as $index => $image) : ?>
                            <?php if (is_array($image)) { $this->renderPhotoRow($prefix, $index, $image); } ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="theobroma-photo-admin__empty" data-photo-empty<?php echo $images !== array() ? ' hidden' : ''; ?>>
                        <span class="dashicons dashicons-camera-alt" aria-hidden="true"></span><strong>Добавьте первые фотографии</strong><p>Можно выбрать несколько готовых изображений или загрузить новые файлы в медиатеку.</p>
                    </div>
                    <template data-photo-template><?php $this->renderPhotoRow($prefix, '__INDEX__', array('attachment_id' => 0, 'alt' => '', 'caption' => '')); ?></template>
                </div>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $image */
    private function renderPhotoRow(string $prefix, int|string $index, array $image): void
    {
        $attachmentId = (int) ($image['attachment_id'] ?? 0);
        $imageHtml = $attachmentId > 0
            ? wp_get_attachment_image($attachmentId, 'medium', false, array('class' => 'theobroma-photo-card__image', 'alt' => ''))
            : '<img class="theobroma-photo-card__image" alt="">';
        $rowPrefix = $prefix . '[images][' . $index . ']';
        ?>
        <article class="theobroma-photo-card" draggable="true" data-photo-row data-attachment-id="<?php echo esc_attr((string) $attachmentId); ?>">
            <div class="theobroma-photo-card__preview"><?php echo $imageHtml; ?><span class="theobroma-photo-card__number" data-photo-number><?php echo esc_html(is_int($index) ? str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) : '00'); ?></span><span class="dashicons dashicons-move" aria-hidden="true"></span></div>
            <input type="hidden" name="<?php echo esc_attr($rowPrefix . '[attachment_id]'); ?>" value="<?php echo esc_attr((string) $attachmentId); ?>" data-photo-id>
            <div class="theobroma-photo-card__fields">
                <label class="theobroma-photo-field"><span>Alt-текст</span><input type="text" name="<?php echo esc_attr($rowPrefix . '[alt]'); ?>" value="<?php echo esc_attr((string) ($image['alt'] ?? '')); ?>" placeholder="Что изображено на фото"></label>
                <label class="theobroma-photo-field"><span>Подпись <small>необязательно</small></span><input type="text" name="<?php echo esc_attr($rowPrefix . '[caption]'); ?>" value="<?php echo esc_attr((string) ($image['caption'] ?? '')); ?>" placeholder="Короткая деталь или кейс"></label>
            </div>
            <div class="theobroma-photo-card__actions">
                <button class="button" type="button" data-move-photo="up" aria-label="Переместить фотографию вверх"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
                <button class="button" type="button" data-move-photo="down" aria-label="Переместить фотографию вниз"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
                <button class="button-link-delete" type="button" data-remove-photo><span class="dashicons dashicons-trash" aria-hidden="true"></span><span>Удалить</span></button>
            </div>
        </article>
        <?php
    }

    private function textField(string $prefix, string $key, string $label, string $value): void
    {
        ?><label class="theobroma-photo-field"><span><?php echo esc_html($label); ?></span><input type="text" name="<?php echo esc_attr($prefix . '[' . $key . ']'); ?>" value="<?php echo esc_attr($value); ?>"></label><?php
    }

    /** @return array<string, array{title: string, hint: string}> */
    private function labels(): array
    {
        return array(
            'home' => array('title' => 'Главная', 'hint' => 'Живая история бренда'),
            'corporate' => array('title' => 'Корпоративные подарки', 'hint' => 'Примеры оформления и кейсы'),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function collections(): array
    {
        $saved = get_option(Settings::OPTION, null);
        $collections = $this->settings->sanitize($saved);
        if (is_array($saved)) {
            return $collections;
        }

        $ids = $this->defaultImages->ids(5);
        $rows = array_map(static fn (int $id): array => array('attachment_id' => $id, 'alt' => '', 'caption' => ''), $ids);
        $collections['home']['images'] = $rows;
        $collections['corporate']['images'] = array_slice(array_reverse($rows), 0, 3);

        return $collections;
    }
}

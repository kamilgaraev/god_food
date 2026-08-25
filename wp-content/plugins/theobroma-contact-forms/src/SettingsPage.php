<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class SettingsPage
{
    private readonly string $pluginFile;

    public function __construct(private readonly Settings $settings, string $pluginFile = '')
    {
        $this->pluginFile = $pluginFile !== '' ? $pluginFile : dirname(__DIR__) . '/theobroma-contact-forms.php';
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'settings'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }

    public function menu(): void
    {
        add_options_page(
            'Формы заявок Theobroma',
            'Формы заявок',
            'manage_options',
            'theobroma-contact-forms',
            array($this, 'render')
        );
    }

    public function settings(): void
    {
        register_setting('theobroma_contact_forms', Settings::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => fn(mixed $input): array => $this->settings->sanitize(
                is_array($input) ? $input : array(),
                sanitize_email((string) get_option('admin_email', ''))
            ),
            'default' => $this->settings->defaults(sanitize_email((string) get_option('admin_email', ''))),
            'show_in_rest' => false,
        ));
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'settings_page_theobroma-contact-forms') {
            return;
        }
        $script = dirname(__DIR__) . '/assets/admin.js';
        $style = dirname(__DIR__) . '/assets/admin.css';
        wp_enqueue_script(
            'theobroma-contact-forms-admin',
            plugins_url('assets/admin.js', $this->pluginFile),
            array(),
            is_file($script) ? (string) filemtime($script) : '1.1.0',
            array('in_footer' => true)
        );
        wp_enqueue_style(
            'theobroma-contact-forms-admin',
            plugins_url('assets/admin.css', $this->pluginFile),
            array(),
            is_file($style) ? (string) filemtime($style) : '1.1.0'
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $fallbackEmail = sanitize_email((string) get_option('admin_email', ''));
        $stored = get_option(Settings::OPTION, array());
        $values = $this->settings->sanitize(is_array($stored) ? $stored : array(), $fallbackEmail);
        $forms = array('home' => 'Главная страница', 'cooperation' => 'Сотрудничество');
        $fields = array('name' => 'Имя', 'phone' => 'Телефон', 'email' => 'E-mail', 'message' => 'Комментарий');
        ?>
        <div class="wrap">
            <div class="theobroma-forms-admin">
                <header class="theobroma-admin-hero">
                    <div>
                        <span class="theobroma-admin-hero__eyebrow">THEOBROMA · ФОРМЫ</span>
                        <h1>Заявки с сайта</h1>
                        <p>Управляйте составом форм и адресами доставки заявок в одном месте.</p>
                    </div>
                    <div class="theobroma-admin-hero__meta" aria-label="Доступно две формы">
                        <strong>2</strong><span>формы<br>подключено</span>
                    </div>
                </header>

                <form method="post" action="options.php">
                    <?php settings_fields('theobroma_contact_forms'); ?>
                    <nav class="theobroma-form-tabs" role="tablist" aria-label="Выбор формы">
                        <?php foreach ($forms as $formId => $title) : ?>
                            <button class="theobroma-form-tab<?php echo $formId === 'home' ? ' is-active' : ''; ?>" type="button" role="tab" data-form-tab="<?php echo esc_attr($formId); ?>" id="theobroma-form-tab-<?php echo esc_attr($formId); ?>" aria-controls="theobroma-form-panel-<?php echo esc_attr($formId); ?>" aria-selected="<?php echo $formId === 'home' ? 'true' : 'false'; ?>" tabindex="<?php echo $formId === 'home' ? '0' : '-1'; ?>">
                                <span class="dashicons <?php echo $formId === 'home' ? 'dashicons-admin-home' : 'dashicons-groups'; ?>" aria-hidden="true"></span>
                                <span><strong><?php echo esc_html($title); ?></strong><small><?php echo $formId === 'home' ? 'Форма внизу главной' : 'Страница для партнёров'; ?></small></span>
                            </button>
                        <?php endforeach; ?>
                    </nav>

                    <?php foreach ($forms as $formId => $title) : ?>
                        <section class="theobroma-form-panel" id="theobroma-form-panel-<?php echo esc_attr($formId); ?>" role="tabpanel" aria-labelledby="theobroma-form-tab-<?php echo esc_attr($formId); ?>" data-form-panel="<?php echo esc_attr($formId); ?>"<?php echo $formId !== 'home' ? ' hidden' : ''; ?>>
                            <?php $this->renderFormPanel($formId, $title, $values[$formId], $fields); ?>
                        </section>
                    <?php endforeach; ?>

                    <div class="theobroma-save-bar">
                        <span><span class="dashicons dashicons-saved" aria-hidden="true"></span> Изменения применятся сразу после сохранения</span>
                        <button type="submit" class="button button-primary button-large">Сохранить настройки</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /** @param array<string, mixed> $values @param array<string, string> $fields */
    private function renderFormPanel(string $formId, string $title, array $values, array $fields): void
    {
        $enabledCount = count(array_filter($values['fields'], static fn(mixed $field): bool => is_array($field) && !empty($field['enabled'])));
        $customCount = count($values['custom_fields']);
        ?>
        <div class="theobroma-panel-heading">
            <div><span>Настройка формы</span><h2><?php echo esc_html($title); ?></h2></div>
            <span class="theobroma-panel-heading__status"><i></i><?php echo esc_html((string) ($enabledCount + $customCount)); ?> полей активно</span>
        </div>

        <div class="theobroma-settings-grid">
            <section class="theobroma-settings-card theobroma-settings-card--recipient">
                <header class="theobroma-settings-card__header">
                    <span class="theobroma-settings-card__icon dashicons dashicons-email-alt" aria-hidden="true"></span>
                    <div><h3>Получатель заявок</h3><p>На этот адрес будут приходить новые обращения</p></div>
                </header>
                <label class="theobroma-field-label" for="theobroma-recipient-<?php echo esc_attr($formId); ?>">Рабочая почта</label>
                <div class="theobroma-email-field">
                    <span class="dashicons dashicons-email" aria-hidden="true"></span>
                    <input id="theobroma-recipient-<?php echo esc_attr($formId); ?>" type="email" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][recipient]'); ?>" value="<?php echo esc_attr((string) $values['recipient']); ?>" autocomplete="email" required>
                </div>
            </section>

            <section class="theobroma-settings-card theobroma-settings-card--standard">
                <header class="theobroma-settings-card__header">
                    <span class="theobroma-settings-card__icon dashicons dashicons-feedback" aria-hidden="true"></span>
                    <div><h3>Стандартные поля</h3><p>Выберите, что увидит посетитель и что он обязан заполнить</p></div>
                </header>
                <div class="theobroma-standard-fields">
                    <div class="theobroma-standard-fields__labels" aria-hidden="true"><span>Поле</span><span>Показывать</span><span>Обязательное</span></div>
                    <?php foreach ($fields as $fieldId => $label) : ?>
                        <?php $prefix = Settings::OPTION . '[' . $formId . '][fields][' . $fieldId . ']'; ?>
                        <div class="theobroma-standard-field" data-standard-field>
                            <div class="theobroma-standard-field__name"><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($this->fieldHint($fieldId)); ?></small></div>
                            <label class="theobroma-switch"><input type="hidden" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="0"><input class="theobroma-switch__input" type="checkbox" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="1" data-field-enabled <?php checked(!empty($values['fields'][$fieldId]['enabled'])); ?>><span class="theobroma-switch__track" aria-hidden="true"></span><span class="screen-reader-text">Показывать поле «<?php echo esc_html($label); ?>»</span></label>
                            <label class="theobroma-switch"><input type="hidden" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="0"><input class="theobroma-switch__input" type="checkbox" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="1" data-field-required <?php checked(!empty($values['fields'][$fieldId]['required'])); ?>><span class="theobroma-switch__track" aria-hidden="true"></span><span class="screen-reader-text">Сделать поле «<?php echo esc_html($label); ?>» обязательным</span></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="theobroma-settings-card theobroma-settings-card--custom">
            <header class="theobroma-settings-card__header theobroma-settings-card__header--action">
                <div class="theobroma-settings-card__title"><span class="theobroma-settings-card__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span><div><h3>Дополнительные поля</h3><p>Добавьте уточнения, которые нужны именно этой форме</p></div></div>
                <span class="theobroma-count-badge" data-custom-field-count><?php echo esc_html((string) $customCount); ?></span>
            </header>
            <div class="theobroma-custom-fields" data-custom-fields>
                <div class="theobroma-custom-fields__list" data-custom-fields-list>
                    <?php foreach ($values['custom_fields'] as $index => $field) : ?>
                        <?php $this->renderCustomField($formId, (string) $index, $field); ?>
                    <?php endforeach; ?>
                </div>
                <div class="theobroma-custom-fields__empty" data-custom-fields-empty<?php echo $customCount > 0 ? ' hidden' : ''; ?>>
                    <span class="dashicons dashicons-welcome-add-page" aria-hidden="true"></span>
                    <strong>Дополнительных полей пока нет</strong>
                    <p>Добавьте поле, если нужно узнать город, бюджет, дату или другие детали.</p>
                </div>
                <button type="button" class="button theobroma-add-field" data-add-custom-field><span class="dashicons dashicons-plus" aria-hidden="true"></span>Добавить поле</button>
                <template data-custom-field-template>
                    <?php $this->renderCustomField($formId, '__INDEX__', array('key' => '', 'label' => '', 'type' => 'text', 'placeholder' => '', 'required' => false, 'options' => array())); ?>
                </template>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $field */
    private function renderCustomField(string $formId, string $index, array $field): void
    {
        $prefix = Settings::OPTION . '[' . $formId . '][custom_fields][' . $index . ']';
        $types = array(
            'text' => 'Текст',
            'email' => 'E-mail',
            'tel' => 'Телефон',
            'number' => 'Число',
            'textarea' => 'Многострочный текст',
            'select' => 'Выпадающий список',
        );
        $options = isset($field['options']) && is_array($field['options'])
            ? implode("\n", $field['options'])
            : (string) ($field['options'] ?? '');
        ?>
        <article class="theobroma-custom-field" data-custom-field-row>
            <header class="theobroma-custom-field__header">
                <div><span class="theobroma-custom-field__number" data-custom-field-number><?php echo esc_html((string) ((int) $index + 1)); ?></span><strong>Дополнительное поле</strong></div>
                <div class="theobroma-custom-fields__actions">
                    <button type="button" class="button" data-move-custom-field="up" aria-label="Переместить вверх"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button" data-move-custom-field="down" aria-label="Переместить вниз"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button-link-delete" data-remove-custom-field><span class="dashicons dashicons-trash" aria-hidden="true"></span>Удалить</button>
                </div>
            </header>
            <div class="theobroma-custom-field__grid">
                <input type="hidden" name="<?php echo esc_attr($prefix . '[key]'); ?>" value="<?php echo esc_attr((string) ($field['key'] ?? '')); ?>" data-custom-field-key>
                <label class="theobroma-field-control"><span>Название поля</span><input type="text" name="<?php echo esc_attr($prefix . '[label]'); ?>" value="<?php echo esc_attr((string) ($field['label'] ?? '')); ?>" placeholder="Например, город доставки" required></label>
                <label class="theobroma-field-control"><span>Подсказка внутри</span><input type="text" name="<?php echo esc_attr($prefix . '[placeholder]'); ?>" value="<?php echo esc_attr((string) ($field['placeholder'] ?? '')); ?>" placeholder="Например, Москва"></label>
                <label class="theobroma-field-control"><span>Тип поля</span><select name="<?php echo esc_attr($prefix . '[type]'); ?>" data-custom-field-type>
                    <?php foreach ($types as $type => $label) : ?><option value="<?php echo esc_attr($type); ?>" <?php selected(($field['type'] ?? 'text') === $type); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select></label>
                <label class="theobroma-custom-field__required"><input type="hidden" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="0"><input class="theobroma-switch__input" type="checkbox" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="1" <?php checked(!empty($field['required'])); ?>><span class="theobroma-switch__track" aria-hidden="true"></span><span>Обязательное поле</span></label>
                <label class="theobroma-field-control theobroma-field-control--options" data-custom-field-options-wrap><span>Варианты списка <small>по одному в строке</small></span><textarea name="<?php echo esc_attr($prefix . '[options]'); ?>" rows="4" placeholder="Вариант 1&#10;Вариант 2" data-custom-field-options><?php echo esc_html($options); ?></textarea></label>
            </div>
        </article>
        <?php
    }

    private function fieldHint(string $fieldId): string
    {
        return array('name' => 'Однострочный текст', 'phone' => 'Номер телефона', 'email' => 'Электронная почта', 'message' => 'Комментарий клиента')[$fieldId] ?? '';
    }
}

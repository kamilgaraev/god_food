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
            <h1><?php echo esc_html('Формы заявок'); ?></h1>
            <p><?php echo esc_html('Настройте поля и получателя отдельно для каждой формы. Обязательное стандартное поле автоматически показывается.'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('theobroma_contact_forms'); ?>
                <?php foreach ($forms as $formId => $title) : ?>
                    <h2><?php echo esc_html($title); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="theobroma-recipient-<?php echo esc_attr($formId); ?>">Получатель заявок</label></th>
                            <td><input class="regular-text" id="theobroma-recipient-<?php echo esc_attr($formId); ?>" type="email" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][recipient]'); ?>" value="<?php echo esc_attr((string) $values[$formId]['recipient']); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row">Поля формы</th>
                            <td>
                                <table class="widefat striped" style="max-width:680px">
                                    <thead><tr><th>Поле</th><th>Показывать</th><th>Обязательное</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($fields as $fieldId => $label) : ?>
                                        <tr>
                                            <td><?php echo esc_html($label); ?></td>
                                            <td><input type="hidden" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][fields][' . $fieldId . '][enabled]'); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][fields][' . $fieldId . '][enabled]'); ?>" value="1" <?php checked(!empty($values[$formId]['fields'][$fieldId]['enabled'])); ?>> Показывать</label></td>
                                            <td><input type="hidden" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][fields][' . $fieldId . '][required]'); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION . '[' . $formId . '][fields][' . $fieldId . '][required]'); ?>" value="1" <?php checked(!empty($values[$formId]['fields'][$fieldId]['required'])); ?>> Обязательное</label></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Дополнительные поля</th>
                            <td>
                                <div class="theobroma-custom-fields" data-custom-fields>
                                    <table class="widefat striped theobroma-custom-fields__table">
                                        <thead><tr><th>Название и подсказка</th><th>Тип</th><th>Обязательное</th><th>Варианты списка</th><th>Порядок</th></tr></thead>
                                        <tbody data-custom-fields-list>
                                            <?php foreach ($values[$formId]['custom_fields'] as $index => $field) : ?>
                                                <?php $this->renderCustomField($formId, (string) $index, $field); ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p><button type="button" class="button" data-add-custom-field>Добавить поле</button></p>
                                    <template data-custom-field-template>
                                        <?php $this->renderCustomField($formId, '__INDEX__', array(
                                            'key' => '',
                                            'label' => '',
                                            'type' => 'text',
                                            'placeholder' => '',
                                            'required' => false,
                                            'options' => array(),
                                        )); ?>
                                    </template>
                                </div>
                                <p class="description">Поля выводятся в указанном порядке после стандартных. Для списка укажите по одному варианту в строке.</p>
                            </td>
                        </tr>
                    </table>
                <?php endforeach; ?>
                <?php submit_button('Сохранить настройки'); ?>
            </form>
        </div>
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
        <tr data-custom-field-row>
            <td>
                <input type="hidden" name="<?php echo esc_attr($prefix . '[key]'); ?>" value="<?php echo esc_attr((string) ($field['key'] ?? '')); ?>" data-custom-field-key>
                <input class="regular-text" type="text" name="<?php echo esc_attr($prefix . '[label]'); ?>" value="<?php echo esc_attr((string) ($field['label'] ?? '')); ?>" placeholder="Название поля" required>
                <input class="regular-text" type="text" name="<?php echo esc_attr($prefix . '[placeholder]'); ?>" value="<?php echo esc_attr((string) ($field['placeholder'] ?? '')); ?>" placeholder="Подсказка внутри поля">
            </td>
            <td><select name="<?php echo esc_attr($prefix . '[type]'); ?>" data-custom-field-type>
                <?php foreach ($types as $type => $label) : ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected(($field['type'] ?? 'text') === $type); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></td>
            <td>
                <input type="hidden" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="0">
                <label><input type="checkbox" name="<?php echo esc_attr($prefix . '[required]'); ?>" value="1" <?php checked(!empty($field['required'])); ?>> Да</label>
            </td>
            <td><textarea name="<?php echo esc_attr($prefix . '[options]'); ?>" rows="4" placeholder="Вариант 1&#10;Вариант 2" data-custom-field-options><?php echo esc_html($options); ?></textarea></td>
            <td class="theobroma-custom-fields__actions">
                <button type="button" class="button" data-move-custom-field="up" aria-label="Переместить вверх">↑</button>
                <button type="button" class="button" data-move-custom-field="down" aria-label="Переместить вниз">↓</button>
                <button type="button" class="button-link-delete" data-remove-custom-field>Удалить</button>
            </td>
        </tr>
        <?php
    }
}

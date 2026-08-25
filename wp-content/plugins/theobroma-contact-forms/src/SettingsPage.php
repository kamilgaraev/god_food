<?php

declare(strict_types=1);

namespace Theobroma\ContactForms;

final class SettingsPage
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'settings'));
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
            <p><?php echo esc_html('Настройте поля и получателя отдельно для каждой формы. Обязательное поле автоматически показывается.'); ?></p>
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
                    </table>
                <?php endforeach; ?>
                <?php submit_button('Сохранить настройки'); ?>
            </form>
        </div>
        <?php
    }
}

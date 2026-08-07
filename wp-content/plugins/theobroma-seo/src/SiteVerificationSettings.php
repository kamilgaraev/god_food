<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class SiteVerificationSettings
{
    public const OPTION = 'theobroma_yandex_verification';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void
    {
        add_options_page(
            'SEO Theobroma',
            'SEO Theobroma',
            'manage_options',
            'theobroma-seo',
            [$this, 'render']
        );
    }

    public function settings(): void
    {
        register_setting('theobroma_seo', self::OPTION, [
            'type' => 'string',
            'sanitize_callback' => [new SiteVerificationRenderer(), 'sanitize'],
            'default' => '',
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $token = (string) get_option(self::OPTION, '');
        ?>
        <div class="wrap">
            <h1>SEO Theobroma</h1>
            <p>Введите токен из Яндекс.Вебмастера. Пока поле пустое, verification meta tag не выводится.</p>
            <form method="post" action="options.php">
                <?php settings_fields('theobroma_seo'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="theobroma-yandex-verification">Токен Яндекс.Вебмастера</label></th>
                        <td><input id="theobroma-yandex-verification" name="<?php echo esc_attr(self::OPTION); ?>" value="<?php echo esc_attr($token); ?>" pattern="[A-Za-z0-9_-]{8,128}" class="regular-text" autocomplete="off"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

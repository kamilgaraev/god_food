<?php
declare(strict_types=1);

namespace Theobroma\OneC\Admin;

use Theobroma\OneC\Settings\Settings;

final class Diagnostics
{
    /** @return array<string, bool> */
    public function checks(): array
    {
        $settings = (new Settings())->get();
        return [
            'WooCommerce активен' => class_exists('WooCommerce'),
            'Расширение XMLWriter доступно' => class_exists('XMLWriter'),
            'HTTPS включён (обязательно в production)' => is_ssl() || wp_get_environment_type() !== 'production',
            'Логин и пароль обмена заданы' => $settings['username'] !== '' && $settings['password_hash'] !== '',
            'Обмен включён' => $settings['enabled'],
        ];
    }
}

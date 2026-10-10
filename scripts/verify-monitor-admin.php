<?php
declare(strict_types=1);

require 'wp-load.php';

$administrators = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ids'));
if (!$administrators) {
    throw new RuntimeException('No administrator is available for the monitor smoke test.');
}
wp_set_current_user((int) $administrators[0]);
if (!current_user_can('manage_options') || !function_exists('theobroma_monitor_render_page')) {
    throw new RuntimeException('The monitoring page is not registered for administrators.');
}

$snapshot = get_option('theobroma_monitor_snapshot');
if (!is_array($snapshot) || empty($snapshot['last_check']['at'])) {
    throw new RuntimeException('The host monitor has not published a dashboard snapshot.');
}

ob_start();
theobroma_monitor_render_page();
$markup = (string) ob_get_clean();
foreach (array('Мониторинг', 'Последние проверки', 'События и ошибки', 'Почта для алертов', 'name="alert_email"') as $expected) {
    if (!str_contains($markup, $expected)) {
        throw new RuntimeException('Missing admin monitoring section: ' . $expected);
    }
}
if (!is_email(theobroma_monitor_alert_email()) || str_contains($markup, 'smtp_password')) {
    throw new RuntimeException('Monitoring page did not protect its mail configuration.');
}

echo "Admin monitoring page and snapshot: OK\n";

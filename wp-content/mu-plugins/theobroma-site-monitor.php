<?php
/** Admin view for the host-side Theobroma monitor. */
declare(strict_types=1);

function theobroma_monitor_admin_url(string $notice = ''): string {
    $url = admin_url('admin.php?page=theobroma-monitor');
    return $notice === '' ? $url : add_query_arg('monitor_notice', $notice, $url);
}

function theobroma_monitor_alert_email(): string {
    $configured = (string) get_option('theobroma_monitor_alert_email', '');
    return is_email($configured) ? $configured : (string) get_option('admin_email');
}

add_action('admin_menu', static function (): void {
    $hook = add_menu_page(
        'Мониторинг сайта',
        'Мониторинг',
        'manage_options',
        'theobroma-monitor',
        'theobroma_monitor_render_page',
        'dashicons-chart-area',
        58
    );
    add_action('admin_enqueue_scripts', static function (string $current_hook) use ($hook): void {
        if ($current_hook !== $hook) {
            return;
        }
        $file = __DIR__ . '/theobroma-site-monitor.css';
        wp_enqueue_style(
            'theobroma-site-monitor',
            content_url('mu-plugins/theobroma-site-monitor.css'),
            array(),
            (string) filemtime($file)
        );
    });
});

function theobroma_monitor_render_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.');
    }

    $snapshot = (array) get_option('theobroma_monitor_snapshot', array());
    $last = (array) ($snapshot['last_check'] ?? array());
    $checked_at = isset($last['at']) ? strtotime((string) $last['at']) : false;
    $fresh = $checked_at && $checked_at >= time() - 6 * MINUTE_IN_SECONDS;
    $site_up = ($last['site'] ?? '') === 'up';
    $active = array_values(array_filter((array) ($snapshot['active'] ?? array()), 'is_string'));
    $checks = array_reverse((array) ($snapshot['checks'] ?? array()));
    $events = array_reverse((array) ($snapshot['events'] ?? array()));
    $notice = sanitize_key((string) ($_GET['monitor_notice'] ?? ''));
    $status_label = !$fresh ? 'Нет свежих данных' : ($site_up ? 'Сайт доступен' : 'Сайт недоступен');
    $status_class = !$fresh ? 'muted' : ($site_up ? 'good' : 'bad');
    ?>
    <div class="wrap theobroma-monitor">
        <div class="tm-heading">
            <div>
                <p class="tm-kicker">Theobroma · состояние сайта</p>
                <h1>Мониторинг</h1>
                <p>Проверка каждые две минуты. Здесь показаны сводки без данных покупателей и сырых строк логов.</p>
            </div>
            <a class="button tm-refresh" href="<?php echo esc_url(theobroma_monitor_admin_url()); ?>">Обновить данные</a>
        </div>

        <?php if ($notice === 'saved') : ?>
            <div class="notice notice-success is-dismissible"><p>Адрес для оповещений сохранён. Монитор подхватит его при следующей проверке.</p></div>
        <?php elseif ($notice === 'invalid') : ?>
            <div class="notice notice-error is-dismissible"><p>Введите корректный адрес электронной почты.</p></div>
        <?php elseif ($notice === 'sent') : ?>
            <div class="notice notice-success is-dismissible"><p>Тестовое письмо отправлено.</p></div>
        <?php elseif ($notice === 'mail-failed') : ?>
            <div class="notice notice-error is-dismissible"><p>Тестовое письмо отправить не удалось. Проверьте SMTP-настройки.</p></div>
        <?php endif; ?>

        <div class="tm-grid">
            <section class="tm-card tm-status tm-status--<?php echo esc_attr($status_class); ?>">
                <span class="tm-label">Доступность</span>
                <strong><?php echo esc_html($status_label); ?></strong>
                <small><?php echo $checked_at ? esc_html('Проверено ' . wp_date('d.m.Y в H:i', $checked_at)) : 'Проверок пока нет'; ?></small>
            </section>
            <section class="tm-card">
                <span class="tm-label">HTTP 5xx</span>
                <strong><?php echo esc_html((string) (int) ($last['http_5xx'] ?? 0)); ?></strong>
                <small>За последнюю проверку</small>
            </section>
            <section class="tm-card">
                <span class="tm-label">Критические ошибки PHP</span>
                <strong><?php echo esc_html((string) (int) ($last['php'] ?? 0)); ?></strong>
                <small>За последнюю проверку</small>
            </section>
            <section class="tm-card">
                <span class="tm-label">Ошибки JavaScript</span>
                <strong><?php echo esc_html((string) (int) ($last['js'] ?? 0)); ?></strong>
                <small>За последнюю проверку</small>
            </section>
        </div>

        <div class="tm-columns">
            <section class="tm-panel">
                <div class="tm-panel-heading">
                    <h2>Оповещения</h2>
                    <span class="tm-pill <?php echo $active ? 'tm-pill--bad' : 'tm-pill--good'; ?>">
                        <?php echo $active ? esc_html('Активно: ' . count($active)) : 'Активных нет'; ?>
                    </span>
                </div>
                <?php if ($active) : ?>
                    <ul class="tm-alerts"><?php foreach ($active as $alert) : ?><li><?php echo esc_html($alert); ?></li><?php endforeach; ?></ul>
                <?php else : ?>
                    <p class="tm-subtle">Новых срабатываний нет. При проблеме монитор отправит письмо на указанный адрес.</p>
                <?php endif; ?>
            </section>
            <section class="tm-panel">
                <h2>Почта для алертов</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="theobroma_monitor_save_email">
                    <?php wp_nonce_field('theobroma_monitor_save_email'); ?>
                    <label for="tm-alert-email">Адрес получателя</label>
                    <div class="tm-form-row">
                        <input id="tm-alert-email" name="alert_email" type="email" required autocomplete="email" value="<?php echo esc_attr(theobroma_monitor_alert_email()); ?>">
                        <button class="button button-primary" type="submit">Сохранить</button>
                    </div>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-test-form">
                    <input type="hidden" name="action" value="theobroma_monitor_test_email">
                    <?php wp_nonce_field('theobroma_monitor_test_email'); ?>
                    <button class="button" type="submit">Отправить тестовое письмо</button>
                </form>
                <p class="tm-subtle">После сохранения новый адрес применяется при следующей проверке, обычно в течение двух минут.</p>
            </section>
        </div>

        <div class="tm-columns tm-columns--logs">
            <section class="tm-panel">
                <h2>Последние проверки</h2>
                <?php if (!$checks) : ?><p class="tm-subtle">Записи появятся после ближайшей проверки.</p><?php else : ?>
                <div class="tm-table-scroll"><table class="widefat striped tm-table"><thead><tr><th>Время</th><th>Сайт</th><th>5xx</th><th>PHP</th><th>JS</th></tr></thead><tbody>
                    <?php foreach (array_slice($checks, 0, 12) as $check) : $check = (array) $check; ?>
                    <tr>
                        <td><?php echo esc_html(theobroma_monitor_format_time((string) ($check['at'] ?? ''))); ?></td>
                        <td><span class="tm-dot <?php echo ($check['site'] ?? '') === 'up' ? 'tm-dot--good' : 'tm-dot--bad'; ?>"></span><?php echo ($check['site'] ?? '') === 'up' ? 'Доступен' : 'Ошибка'; ?></td>
                        <td><?php echo esc_html((string) (int) ($check['http_5xx'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($check['php'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($check['js'] ?? 0)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </section>
            <section class="tm-panel">
                <h2>События и ошибки</h2>
                <?php if (!$events) : ?><p class="tm-subtle">Ошибок и срабатываний пока нет.</p><?php else : ?>
                <ul class="tm-event-list">
                    <?php foreach (array_slice($events, 0, 16) as $event) : $event = (array) $event; ?>
                    <li>
                        <span class="tm-dot <?php echo ($event['level'] ?? '') === 'success' ? 'tm-dot--good' : 'tm-dot--bad'; ?>"></span>
                        <div><strong><?php echo esc_html((string) ($event['title'] ?? 'Событие')); ?></strong>
                            <p><?php echo esc_html((string) ($event['detail'] ?? '')); ?></p>
                            <small><?php echo esc_html(theobroma_monitor_format_time((string) ($event['at'] ?? ''))); ?></small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
}

function theobroma_monitor_format_time(string $value): string {
    $timestamp = strtotime($value);
    return $timestamp ? wp_date('d.m.Y H:i', $timestamp) : '—';
}

add_action('admin_post_theobroma_monitor_save_email', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.');
    }
    check_admin_referer('theobroma_monitor_save_email');
    $email = sanitize_email((string) wp_unslash($_POST['alert_email'] ?? ''));
    if (!is_email($email)) {
        wp_safe_redirect(theobroma_monitor_admin_url('invalid'));
        exit;
    }
    update_option('theobroma_monitor_alert_email', $email, false);
    wp_safe_redirect(theobroma_monitor_admin_url('saved'));
    exit;
});

add_action('admin_post_theobroma_monitor_test_email', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.');
    }
    check_admin_referer('theobroma_monitor_test_email');
    $sent = wp_mail(
        theobroma_monitor_alert_email(),
        '[Theobroma] Проверка оповещений',
        'Тестовое письмо из раздела «Мониторинг». Адрес для алертов настроен.'
    );
    wp_safe_redirect(theobroma_monitor_admin_url($sent ? 'sent' : 'mail-failed'));
    exit;
});

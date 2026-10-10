<?php
/**
 * Prevent a staging copy from sending real orders, payments and notifications.
 * Stored integration credentials remain unchanged.
 */
if (getenv('THEOBROMA_ENVIRONMENT') !== 'staging') {
    return;
}
if (!defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}
add_filter('pre_option_blog_public', static fn() => '0');
add_action('phpmailer_init', static function ($mailer) {
    $mailer->isSMTP();
    $mailer->Host = 'mailpit';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
    $mailer->Username = '';
    $mailer->Password = '';
}, PHP_INT_MAX);
add_filter('pre_http_request', static function ($response, $args, $url) {
    return new WP_Error('theobroma_staging_network', 'External requests are disabled on the staging site.');
}, PHP_INT_MAX, 3);
add_filter('woocommerce_available_payment_gateways', static fn() => array(), PHP_INT_MAX);
add_filter('action_scheduler_allow_async_request_runner', '__return_false', PHP_INT_MAX);
add_action('send_headers', static function () {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
});

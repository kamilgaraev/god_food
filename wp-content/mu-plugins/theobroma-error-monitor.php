<?php
/**
 * Minimal, privacy-conscious client error signal for the host monitor.
 */
declare(strict_types=1);

add_action('rest_api_init', static function (): void {
    register_rest_route('theobroma/v1', '/client-error', array(
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request): WP_REST_Response {
            if (strlen($request->get_body()) > 512) {
                return new WP_REST_Response(null, 413);
            }

            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $limit_key = 'theobroma_js_' . hash('sha256', $ip);
            $count = (int) get_transient($limit_key);
            if ($count >= 10) {
                return new WP_REST_Response(null, 204);
            }

            $data = $request->get_json_params();
            if (!is_array($data) || !in_array($data['kind'] ?? '', array('error', 'rejection'), true)) {
                return new WP_REST_Response(null, 400);
            }

            $path = (string) ($data['path'] ?? '');
            if (strlen($path) > 180 || !preg_match('~^/[a-zA-Z0-9_./-]*$~', $path)) {
                $path = '';
            }
            $line = max(0, min(100000, (int) ($data['line'] ?? 0)));
            set_transient($limit_key, $count + 1, MINUTE_IN_SECONDS);
            error_log('THEOBROMA_JS_ERROR ' . wp_json_encode(array(
                'kind' => $data['kind'],
                'path' => $path,
                'line' => $line,
            )));
            return new WP_REST_Response(null, 204);
        },
    ));
});

add_action('wp_enqueue_scripts', static function (): void {
    $path = get_template_directory() . '/assets/js/client-error-monitor.js';
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_script(
        'theobroma-client-error-monitor',
        get_template_directory_uri() . '/assets/js/client-error-monitor.js',
        array(),
        (string) filemtime($path),
        array('strategy' => 'defer', 'in_footer' => true)
    );
    wp_add_inline_script(
        'theobroma-client-error-monitor',
        'window.theobromaErrorEndpoint=' . wp_json_encode(rest_url('theobroma/v1/client-error')) . ';',
        'before'
    );
}, 100);

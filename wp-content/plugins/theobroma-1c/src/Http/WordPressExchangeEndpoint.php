<?php
declare(strict_types=1);

namespace Theobroma\OneC\Http;

use Theobroma\OneC\CommerceMl\OrderWriter;
use Theobroma\OneC\Orders\{WooOrderMapper, WooOrderRepository};
use Theobroma\OneC\Settings\Settings;
use Theobroma\OneC\Support\ExchangeLogger;

final class WordPressExchangeEndpoint
{
    private const BATCH = 'theobroma_1c_batch_';
    private const ATTEMPTS = 'theobroma_1c_auth_';

    public static function register(): void
    {
        add_action('init', [self::class, 'rewrite']);
        add_filter('query_vars', [self::class, 'queryVars']);
        add_action('template_redirect', [self::class, 'dispatch']);
    }

    public static function rewrite(): void
    {
        add_rewrite_rule('^theobroma-1c/exchange/?$', 'index.php?theobroma_1c_exchange=1', 'top');
    }

    /** @param list<string> $variables @return list<string> */
    public static function queryVars(array $variables): array
    {
        $variables[] = 'theobroma_1c_exchange';
        return $variables;
    }

    public static function dispatch(): void
    {
        if (!(int) get_query_var('theobroma_1c_exchange')) {
            return;
        }
        if ((string) ($_GET['type'] ?? '') !== 'sale') {
            self::send(new ExchangeResponse(400, "failure\nUnsupported type"));
        }
        if (!is_ssl() && wp_get_environment_type() === 'production') {
            self::send(new ExchangeResponse(403, "failure\nHTTPS required"));
        }

        $bucket = self::authBucket();
        $limiter = new AuthRateLimiter(
            static fn(string $key): int => (int) get_transient(self::ATTEMPTS . $key),
            static function (string $key, int $value): void { set_transient(self::ATTEMPTS . $key, $value, 15 * MINUTE_IN_SECONDS); },
            static function (string $key): void { delete_transient(self::ATTEMPTS . $key); }
        );
        if (!$limiter->allowed($bucket)) {
            self::send(new ExchangeResponse(429, "failure\nToo many authentication attempts", headers: ['Retry-After' => '900']));
        }

        $settings = (new Settings())->get();
        $repository = new WooOrderRepository();
        $mapper = new WooOrderMapper();
        $logger = new ExchangeLogger();
        $token = self::token();
        $controller = new ExchangeController(
            $settings['enabled'],
            new BasicAuthenticator($settings['username'], $settings['password_hash'], 'wp_check_password'),
            static function () use ($repository, $mapper, $settings, $token, $logger): string {
                $pending = $repository->pending($settings['batch_size']);
                set_transient(self::BATCH . $token, array_map(static fn(array $row): array => [
                    'order_id' => (int) $row['order']->get_id(),
                    'revision' => $row['revision'],
                ], $pending), HOUR_IN_SECONDS);
                $logger->info('CommerceML batch generated', ['mode' => 'query', 'result' => 'success', 'order_count' => count($pending)]);
                return (new OrderWriter())->write(array_map(static fn(array $row) => $mapper->map($row['order']), $pending));
            },
            static function () use ($repository, $token, $logger): void {
                $batch = get_transient(self::BATCH . $token);
                if (is_array($batch)) {
                    $repository->acknowledge($batch);
                }
                delete_transient(self::BATCH . $token);
                $logger->info('CommerceML batch acknowledged', ['mode' => 'success', 'result' => 'success', 'order_count' => is_array($batch) ? count($batch) : 0]);
            }
        );

        $mode = sanitize_key((string) ($_GET['mode'] ?? ''));
        $response = $controller->handle($mode, (string) ($_SERVER['PHP_AUTH_USER'] ?? ''), (string) ($_SERVER['PHP_AUTH_PW'] ?? ''));
        if ($response->status === 401) {
            $limiter->failure($bucket);
            $logger->info('CommerceML authentication failed', ['mode' => $mode, 'result' => 'unauthorized']);
        } elseif ($response->status < 400) {
            $limiter->success($bucket);
        }
        self::send($response);
    }

    private static function send(ExchangeResponse $response): never
    {
        status_header($response->status);
        header('Content-Type: ' . $response->contentType);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response->body;
        exit;
    }

    private static function token(): string
    {
        $raw = (string) ($_COOKIE['theobroma_1c_session'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'anonymous'));
        return hash_hmac('sha256', $raw, wp_salt('auth'));
    }

    private static function authBucket(): string
    {
        return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('auth'));
    }
}

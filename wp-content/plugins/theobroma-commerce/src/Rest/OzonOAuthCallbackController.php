<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Rest;

use Theobroma\Commerce\Admin\OzonConnectionAction;
use Theobroma\Commerce\Infrastructure\WpTransport;
use Theobroma\Commerce\Integrations\Ozon\OzonAuthorizationGrant;
use Theobroma\Commerce\Integrations\Ozon\OzonClientFactory;
use Theobroma\Commerce\Integrations\Ozon\WordPressOAuthStateStore;
use Theobroma\Commerce\Integrations\Ozon\WordPressTokenStore;
use Theobroma\Commerce\Support\ProviderException;

final class OzonOAuthCallbackController
{
    public const ROUTE = '/ozon/oauth/callback';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('theobroma-commerce/v1', self::ROUTE, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function redirectUri(): string
    {
        return rest_url('theobroma-commerce/v1' . self::ROUTE);
    }

    public function handle(\WP_REST_Request $request): never
    {
        $state = sanitize_text_field((string) $request->get_param('state'));
        $code = sanitize_text_field((string) $request->get_param('code'));
        $providerError = sanitize_text_field((string) $request->get_param('error'));

        try {
            if ($providerError !== '') {
                throw ProviderException::fromResponse('Ozon authorization was cancelled', 400);
            }

            $settings = get_option('theobroma_commerce_settings', []);
            $factory = new OzonClientFactory(new WpTransport(), new WordPressTokenStore());
            $grant = new OzonAuthorizationGrant(
                new WordPressOAuthStateStore(),
                $factory->authenticatorFromSettings(is_array($settings) ? $settings : [])
            );
            $initiatorId = $grant->complete($state, $code, self::redirectUri());
            $this->notice($initiatorId, 'success', 'Авторизация продавца Ozon завершена.');
        } catch (ProviderException $exception) {
            $status = $exception->statusCode();
            $message = $status > 0
                ? sprintf('Не удалось завершить авторизацию Ozon (HTTP %d).', $status)
                : 'Не удалось соединиться с Ozon для завершения авторизации.';
            $this->notice(get_current_user_id(), 'error', $message);
        } catch (\Throwable) {
            $this->notice(get_current_user_id(), 'error', 'Не удалось завершить авторизацию Ozon.');
        }

        wp_safe_redirect(admin_url('admin.php?page=theobroma-commerce'));
        exit;
    }

    private function notice(int $userId, string $status, string $message): void
    {
        set_transient(
            OzonConnectionAction::NOTICE_PREFIX . $userId,
            compact('status', 'message'),
            60
        );
    }
}


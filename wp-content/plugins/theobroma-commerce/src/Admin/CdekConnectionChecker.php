<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Cdek\CdekClient;
use Theobroma\Commerce\Support\ProviderException;

final class CdekConnectionChecker
{
    /** @return array{status:'success'|'error',message:string} */
    public function check(CdekClient $client): array
    {
        try {
            $client->verifyCredentials();
            return ['status' => 'success', 'message' => 'Подключение к СДЭК установлено.'];
        } catch (\InvalidArgumentException) {
            return ['status' => 'error', 'message' => 'Сначала сохраните Account и Secure password СДЭК.'];
        } catch (ProviderException $exception) {
            $status = $exception->statusCode();
            $message = $status > 0
                ? sprintf('СДЭК отклонил запрос авторизации (HTTP %d).', $status)
                : 'Не удалось соединиться со СДЭК.';

            return ['status' => 'error', 'message' => $message];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'СДЭК отклонил реквизиты или временно недоступен.'];
        }
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Ozon\AccessTokenProvider;
use Theobroma\Commerce\Support\ProviderException;

final class OzonConnectionChecker
{
    /** @return array{status:'success'|'error',message:string} */
    public function check(AccessTokenProvider $tokens): array
    {
        try {
            $tokens->token();
            return ['status' => 'success', 'message' => 'Подключение к Ozon установлено.'];
        } catch (ProviderException $exception) {
            $status = $exception->statusCode();
            $message = $status > 0
                ? sprintf('Ozon отклонил запрос авторизации (HTTP %d).', $status)
                : 'Не удалось соединиться с Ozon.';

            return ['status' => 'error', 'message' => $message];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'Ozon отклонил реквизиты или временно недоступен.'];
        }
    }
}

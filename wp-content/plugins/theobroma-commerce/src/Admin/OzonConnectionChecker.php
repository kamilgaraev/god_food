<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Admin;

use Theobroma\Commerce\Integrations\Ozon\AccessTokenProvider;

final class OzonConnectionChecker
{
    /** @return array{status:'success'|'error',message:string} */
    public function check(AccessTokenProvider $tokens): array
    {
        try {
            $tokens->forget();
            $tokens->token();
            return ['status' => 'success', 'message' => 'Подключение к Ozon установлено.'];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'Ozon отклонил реквизиты или временно недоступен.'];
        }
    }
}

<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Orders;

use Theobroma\Commerce\Support\ProviderException;

/** Only diagnostic fields are displayed; never dump request/response payloads. */
final class OzonFailureReason
{
    public static function describe(\Throwable $exception): string
    {
        if (!$exception instanceof ProviderException) {
            return 'Внутренняя ошибка интеграции (' . get_class($exception) . ').';
        }
        $parts = [];
        $walk = static function (array $data, int $depth = 0) use (&$walk, &$parts): void {
            if ($depth > 6 || count($parts) >= 8) {
                return;
            }
            foreach ($data as $key => $value) {
                if (is_array($value) && (is_int($key) || in_array($key, ['response', 'error', 'errors', 'details', 'data'], true))) {
                    $walk($value, $depth + 1);
                } elseif (is_scalar($value) && in_array($key, ['message', 'error', 'error_description', 'description', 'code', 'field', 'reason'], true)) {
                    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
                    $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email скрыт]', $text) ?? '';
                    $text = preg_replace('/\bBearer\s+\S+|\b(?:token|secret|password|authorization|api[_-]?key)\s*[:=]\s*\S+/i', '[секрет скрыт]', $text) ?? '';
                    if ($text !== '') {
                        $parts[] = mb_substr($text, 0, 350);
                    }
                }
            }
        };
        $walk($exception->context());
        $status = $exception->statusCode();
        $prefix = $status > 0 ? 'HTTP ' . $status . '. ' : 'Ошибка соединения. ';
        $detail = $parts === [] ? 'Провайдер не передал подробную причину (' . $exception->getMessage() . ').' : implode('; ', array_unique(array_slice($parts, 0, 8)));
        return $prefix . mb_substr($detail, 0, 1200);
    }
}

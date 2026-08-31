<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Rest;

use Theobroma\Commerce\Support\ProviderException;

final class DeliveryProviderFailure
{
    /** @param array<string,mixed> $logContext */
    private function __construct(private readonly string $publicMessage, private readonly array $logContext)
    {
    }

    public static function fromException(string $provider, \Throwable $exception): self
    {
        $providerName = $provider === 'ozon' ? 'Ozon' : ($provider === 'cdek' ? 'СДЭК' : 'службой доставки');
        $status = $exception instanceof ProviderException ? $exception->statusCode() : 0;
        $providerContext = $exception instanceof ProviderException ? $exception->context() : [];

        if ($exception instanceof ProviderException && $status === 0) {
            $message = sprintf('Не удалось соединиться с %s API. Попробуйте ещё раз.', $providerName);
        } elseif ($exception instanceof ProviderException && $status > 0) {
            $message = sprintf(
                '%s API отклонил запрос пунктов выдачи (HTTP %d). Проверьте подключение %s в настройках.',
                $providerName,
                $status,
                $providerName
            );
        } else {
            $message = 'Не удалось загрузить пункты выдачи. Попробуйте ещё раз.';
        }

        return new self($message, [
            'source' => 'theobroma-delivery',
            'provider' => $provider,
            'status' => $status,
            'exception' => $exception::class,
            'error' => $exception->getMessage(),
            'provider_context' => $providerContext,
        ]);
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }

    /** @return array<string,mixed> */
    public function logContext(): array
    {
        return $this->logContext;
    }
}

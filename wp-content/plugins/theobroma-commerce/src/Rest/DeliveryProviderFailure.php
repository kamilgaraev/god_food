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
        return self::build($provider, $exception, 'points');
    }

    public static function forQuote(string $provider, \Throwable $exception): self
    {
        return self::build($provider, $exception, 'quote');
    }

    private static function build(string $provider, \Throwable $exception, string $operation): self
    {
        $providerName = $provider === 'ozon' ? 'Ozon' : ($provider === 'cdek' ? 'СДЭК' : 'службой доставки');
        $status = $exception instanceof ProviderException ? $exception->statusCode() : 0;
        $providerContext = $exception instanceof ProviderException ? $exception->context() : [];

        $message = $operation === 'quote'
            ? 'Не удалось рассчитать доставку. Проверьте адрес и попробуйте ещё раз.'
            : sprintf('Не удалось загрузить пункты выдачи %s. Попробуйте ещё раз или выберите другую службу доставки.', $providerName);

        return new self($message, [
            'source' => 'theobroma-delivery',
            'provider' => $provider,
            'operation' => $operation,
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

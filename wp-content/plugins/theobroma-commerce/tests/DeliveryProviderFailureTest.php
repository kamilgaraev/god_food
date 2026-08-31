<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Rest\DeliveryProviderFailure;
use Theobroma\Commerce\Support\ProviderException;

final class DeliveryProviderFailureTest extends TestCase
{
    public function testExplainsOzonHttpFailureWithoutExposingProviderPayload(): void
    {
        $failure = DeliveryProviderFailure::fromException(
            'ozon',
            ProviderException::fromResponse('Ozon request failed', 403, [
                'response' => ['message' => 'forbidden', 'client_secret' => 'secret'],
            ])
        );

        $this->assertSame('Ozon API отклонил запрос пунктов выдачи (HTTP 403). Проверьте подключение Ozon в настройках.', $failure->publicMessage());
        $this->assertSame(403, $failure->logContext()['status']);
        $this->assertSame('[redacted]', $failure->logContext()['provider_context']['response']['client_secret']);
    }

    public function testExplainsTransportFailureSeparately(): void
    {
        $failure = DeliveryProviderFailure::fromException(
            'ozon',
            ProviderException::fromResponse('Provider transport failed', 0)
        );

        $this->assertSame('Не удалось соединиться с Ozon API. Попробуйте ещё раз.', $failure->publicMessage());
    }

    public function testUsesTheSameHelpfulFailureFormatForCdek(): void
    {
        $failure = DeliveryProviderFailure::fromException(
            'cdek',
            ProviderException::fromResponse('CDEK request failed', 401)
        );

        $this->assertSame('СДЭК API отклонил запрос пунктов выдачи (HTTP 401). Проверьте подключение СДЭК в настройках.', $failure->publicMessage());
    }
}

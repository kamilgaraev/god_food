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

        $this->assertSame('Не удалось загрузить пункты выдачи Ozon. Попробуйте ещё раз или выберите другую службу доставки.', $failure->publicMessage());
        $this->assertSame(403, $failure->logContext()['status']);
        $this->assertSame('[redacted]', $failure->logContext()['provider_context']['response']['client_secret']);
    }

    public function testExplainsTransportFailureSeparately(): void
    {
        $failure = DeliveryProviderFailure::fromException(
            'ozon',
            ProviderException::fromResponse('Provider transport failed', 0)
        );

        $this->assertSame('Не удалось загрузить пункты выдачи Ozon. Попробуйте ещё раз или выберите другую службу доставки.', $failure->publicMessage());
    }

    public function testUsesTheSameHelpfulFailureFormatForCdek(): void
    {
        $failure = DeliveryProviderFailure::fromException(
            'cdek',
            ProviderException::fromResponse('CDEK request failed', 401)
        );

        $this->assertSame('Не удалось загрузить пункты выдачи СДЭК. Попробуйте ещё раз или выберите другую службу доставки.', $failure->publicMessage());
    }

    public function testKeepsQuoteDiagnosticsOutOfTheCustomerMessage(): void
    {
        $failure = DeliveryProviderFailure::forQuote(
            'ozon',
            ProviderException::fromResponse('Internal provider failure code 17', 422)
        );

        $this->assertSame('Не удалось рассчитать доставку. Проверьте адрес и попробуйте ещё раз.', $failure->publicMessage());
        $this->assertSame(422, $failure->logContext()['status']);
        $this->assertSame('Internal provider failure code 17', $failure->logContext()['error']);
    }
}

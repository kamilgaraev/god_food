<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Orders\OzonOrderLifecycle;

final class OzonOrderBuyerTest extends TestCase
{
    public function testAddsCurrentOrderEmailToLegacyQuoteAndPreservesDeliveryData(): void
    {
        $payload = [
            'buyer' => ['first_name' => 'Старое', 'phone' => '79990000000'],
            'recipient' => ['recipient_phone' => '79991111111'],
            'delivery_schema' => 'FBS',
            'splits' => [['warehouse_id' => 77, 'items' => [['sku' => 123, 'quantity' => 1]]]],
            'delivery' => ['pick_up' => ['delivery_point_id' => 42]],
        ];
        $result = OzonOrderLifecycle::withBuyer($payload, [
            'first_name' => ' Иван ', 'last_name' => ' Иванов ', 'email' => ' buyer@example.test ',
        ]);
        $this->assertSame('buyer@example.test', $result['buyer']['email']);
        $this->assertSame('Иван', $result['buyer']['first_name']);
        $this->assertSame('Иванов', $result['recipient']['recipient_last_name']);
        $this->assertSame($payload['buyer']['phone'], $result['buyer']['phone']);
        $this->assertSame($payload['recipient']['recipient_phone'], $result['recipient']['recipient_phone']);
        $this->assertSame($payload['delivery'], $result['delivery']);
        $this->assertSame($payload['splits'], $result['splits']);
        $this->assertSame('FBS', $result['delivery_schema']);
        $this->assertSame(false, isset($payload['buyer']['email']));
    }

    public function testUsesOrderEmailInsteadOfStaleQuoteEmail(): void
    {
        $result = OzonOrderLifecycle::withBuyer(['buyer' => ['email' => 'old@example.test']], [
            'first_name' => 'Иван', 'last_name' => 'Иванов', 'email' => 'new@example.test',
        ]);
        $this->assertSame('new@example.test', $result['buyer']['email']);
    }

    public function testRejectsMissingAndInvalidEmailWithoutExposingItsValue(): void
    {
        foreach (['', '   ', 'not-an-email', "buyer@example.test\r\nBcc:other@example.test"] as $email) {
            $exception = $this->assertThrows(static fn () => OzonOrderLifecycle::withBuyer([], [
                'first_name' => 'Иван', 'last_name' => 'Иванов', 'email' => $email,
            ]), \InvalidArgumentException::class);
            $this->assertSame('Для отправления Ozon укажите корректный email покупателя в заказе.', $exception->getMessage());
        }
    }
}

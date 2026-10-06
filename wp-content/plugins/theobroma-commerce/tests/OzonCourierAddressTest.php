<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\OzonCourierAddress;

final class OzonCourierAddressTest extends TestCase
{
    public function testRepairsSavedCoordinatesOnlyPayloadFromBillingAddress(): void
    {
        $payload = ['delivery' => ['courier' => ['coordinates' => ['latitude' => 55.9, 'longitude' => 37.5]]], 'splits' => [['warehouse_id' => 777]]];
        $result = (new OzonCourierAddress())->apply($payload, ['address_1' => '', 'country' => ''], [
            'country' => 'RU', 'city' => 'посёлок совхоза «Марфино»',
            'address_1' => 'посёлок совхоза «Марфино», 19', 'address_2' => 'Частный дом',
        ]);

        $this->assertSame('Россия', $result['delivery']['courier']['country']);
        $this->assertSame('посёлок совхоза «Марфино»', $result['delivery']['courier']['city']);
        $this->assertSame('19', $result['delivery']['courier']['house_number']);
        $this->assertSame($payload['delivery']['courier']['coordinates'], $result['delivery']['courier']['coordinates']);
        $this->assertSame($payload['splits'], $result['splits']);
    }

    public function testBuildsCourierAddressForCheckoutWithLetterAndBuilding(): void
    {
        $result = (new OzonCourierAddress())->build([
            'country' => 'RU', 'city' => 'Казань', 'address' => 'проспект Космонавтов, д. 42А корпус 2',
        ]);

        $this->assertSame('Россия', $result['country']);
        $this->assertSame('Казань', $result['city']);
        $this->assertSame('проспект Космонавтов', $result['street']);
        $this->assertSame('42А корпус 2', $result['house_number']);
    }

    public function testUsesShippingAddressWithoutMixingInBillingAddress(): void
    {
        $result = (new OzonCourierAddress())->apply(['delivery' => ['courier' => []]], [
            'country' => 'BY', 'city' => 'Минск', 'address_1' => 'улица Ленина, 7/2',
        ], ['country' => 'RU', 'city' => 'Москва', 'address_1' => 'улица Ленина, 19']);

        $this->assertSame('BY', $result['delivery']['courier']['country']);
        $this->assertSame('Минск', $result['delivery']['courier']['city']);
        $this->assertSame('7/2', $result['delivery']['courier']['house_number']);
    }

    public function testKeepsPickupPayloadUnchanged(): void
    {
        $payload = ['delivery' => ['pick_up' => ['map_point_id' => 125]]];
        $this->assertSame($payload, (new OzonCourierAddress())->apply($payload, [], []));
    }

    public function testRejectsMissingHouseInsteadOfInventingIt(): void
    {
        $exception = $this->assertThrows(static fn () => (new OzonCourierAddress())->build([
            'country' => 'RU', 'city' => 'Казань', 'address' => 'улица 8 Марта',
        ]), \InvalidArgumentException::class);
        $this->assertTrue(str_contains($exception->getMessage(), 'house_number'));
    }

    public function testRejectsMissingOrOversizedCountry(): void
    {
        foreach (['', str_repeat('я', 51)] as $country) {
            $exception = $this->assertThrows(static fn () => (new OzonCourierAddress())->build([
                'country' => $country, 'city' => 'Казань', 'address' => 'улица Ленина, 19',
            ]), \InvalidArgumentException::class);
            $this->assertTrue(str_contains($exception->getMessage(), 'country'));
        }
    }
}

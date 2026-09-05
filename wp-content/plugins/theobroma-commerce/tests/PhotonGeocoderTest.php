<?php

declare(strict_types=1);
namespace Theobroma\Commerce\Tests;
use Theobroma\Commerce\Checkout\PhotonGeocoder;

final class PhotonGeocoderTest extends TestCase
{
    public function testNormalizesGeoJsonAndSkipsInvalidCoordinates(): void
    {
        $geocoder = new PhotonGeocoder(static fn (string $url): array => ['status' => 200, 'body' => json_encode(['features' => [
            ['geometry' => ['coordinates' => [49.134, 55.777]], 'properties' => ['city' => 'Казань', 'street' => 'Спартаковская улица', 'housenumber' => '14', 'postcode' => '420107', 'country' => 'Россия']],
            ['geometry' => ['coordinates' => [999, 55]], 'properties' => ['name' => 'Invalid']],
        ]])]);
        $items = $geocoder->suggestions('Казань Спартаковская 14');
        $this->assertSame(1, count($items));
        $this->assertSame('Казань', $items[0]['city']);
        $this->assertSame('Спартаковская улица, 14', $items[0]['address']);
        $this->assertSame(['latitude' => 55.777, 'longitude' => 49.134], $geocoder->coordinates('Казань Спартаковская 14'));
    }

    public function testRejectsCityOnlyForCourier(): void
    {
        $geocoder = new PhotonGeocoder(static fn (string $url): array => ['status' => 200, 'body' => json_encode(['features' => [
            ['geometry' => ['coordinates' => [76.9, 43.2]], 'properties' => ['name' => 'Алматы', 'osm_value' => 'city']],
        ]])]);
        $this->assertSame('Алматы', $geocoder->suggestions('Алматы')[0]['city']);
        try { $geocoder->coordinates('Алматы'); } catch (\RuntimeException $e) {
            $this->assertSame(true, str_contains($e->getMessage(), 'адрес дома'));
            return;
        }
        $this->assertSame(true, false);
    }

    public function testRejectsMalformedProviderResponse(): void
    {
        $geocoder = new PhotonGeocoder(static fn (string $url): array => ['status' => 200, 'body' => '{}']);
        $this->assertSame([], $geocoder->suggestions('Ка'));
        try { $geocoder->suggestions('Казань'); } catch (\RuntimeException $e) {
            $this->assertSame('Некорректный ответ поиска адресов.', $e->getMessage());
            return;
        }
        $this->assertSame(true, false);
    }
}

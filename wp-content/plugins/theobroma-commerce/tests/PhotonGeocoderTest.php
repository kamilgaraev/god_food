<?php

declare(strict_types=1);
namespace Theobroma\Commerce\Tests;
use Theobroma\Commerce\Checkout\PhotonGeocoder;

final class PhotonGeocoderTest extends TestCase
{
    public function testNormalizesPostalAddressWithoutChangingBuildingNumber(): void
    {
        $queries = [];
        $geocoder = new PhotonGeocoder(static function (string $url) use (&$queries): array {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $queries[] = $params['q'];
            return ['status' => 200, 'body' => '{"features":[]}'];
        });
        $geocoder->suggestions('Россия, Республика Татарстан, г Казань, Спартаковская ул, д. 12, офис 223');
        $geocoder->suggestions('Россия, г. Самара, ул. Ленина, д. 8/2, кв. 19');
        $this->assertSame('Россия Татарстан Казань Спартаковская 12', $queries[0]);
        $this->assertSame('Россия Самара Ленина 8/2', $queries[1]);
    }

    public function testCitySearchFiltersCountryAndExcludesBuildings(): void
    {
        $geocoder = new PhotonGeocoder(static function (string $url): array {
            if (!str_contains($url, 'countrycode=KZ') || !str_contains($url, 'osm_tag=place:city')) throw new \RuntimeException('Missing filters');
            $features = [];
            foreach ([['Алматы', 'KZ', 'city'], ['Алматы', 'RU', 'city'], ['Магазин', 'KZ', 'shop']] as $row) {
                $features[] = ['geometry' => ['coordinates' => [76.9, 43.2]], 'properties' => ['name' => $row[0], 'countrycode' => $row[1], 'osm_value' => $row[2]]];
            }
            return ['status' => 200, 'body' => json_encode(['features' => $features])];
        });
        $items = $geocoder->search('Ал', 'KZ', true);
        $this->assertSame(1, count($items));
        $this->assertSame('Алматы', $items[0]['city']);
    }

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

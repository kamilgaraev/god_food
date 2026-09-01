<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\YandexGeocoder;

final class YandexGeocoderTest extends TestCase
{
    public function testReturnsAddressSuggestionsWithCoordinatesAndViewport(): void
    {
        $geocoder = new YandexGeocoder(static fn (string $url): array => [
            'status' => 200,
            'body' => json_encode([
                'response' => ['GeoObjectCollection' => ['featureMember' => [[
                    'GeoObject' => [
                        'name' => 'проспект Космонавтов, 42А',
                        'description' => 'Казань, Республика Татарстан, Россия',
                        'Point' => ['pos' => '49.201 55.793'],
                        'boundedBy' => ['Envelope' => [
                            'lowerCorner' => '49.191 55.783',
                            'upperCorner' => '49.211 55.803',
                        ]],
                        'metaDataProperty' => ['GeocoderMetaData' => ['Address' => [
                            'postal_code' => '420081',
                            'Components' => [
                                ['kind' => 'country', 'name' => 'Россия'],
                                ['kind' => 'province', 'name' => 'Республика Татарстан'],
                                ['kind' => 'locality', 'name' => 'Казань'],
                                ['kind' => 'street', 'name' => 'проспект Космонавтов'],
                                ['kind' => 'house', 'name' => '42А'],
                            ],
                        ]]],
                    ],
                ]]]],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $suggestions = $geocoder->suggestions('Казань, Космонавтов 42А', 'secret-key', 5);

        $this->assertSame('проспект Космонавтов, 42А, Казань, Республика Татарстан, Россия', $suggestions[0]['label']);
        $this->assertSame(55.793, $suggestions[0]['latitude']);
        $this->assertSame(49.201, $suggestions[0]['longitude']);
        $this->assertSame(55.783, $suggestions[0]['viewport']['left_bottom']['lat']);
        $this->assertSame(49.211, $suggestions[0]['viewport']['right_top']['long']);
        $this->assertSame('Казань', $suggestions[0]['city']);
        $this->assertSame('420081', $suggestions[0]['postcode']);
        $this->assertSame('проспект Космонавтов, 42А', $suggestions[0]['address']);
    }
}

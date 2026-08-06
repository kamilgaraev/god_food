<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class SchemaFactory
{
    /** @param array<string, string> $data
     *  @return array<string, mixed>
     */
    public function site(array $data): array
    {
        $organizationId = rtrim($data['url'], '/') . '/#organization';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $organizationId,
                    'name' => $data['name'],
                    'url' => $data['url'],
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $data['logo'],
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => rtrim($data['url'], '/') . '/#website',
                    'url' => $data['url'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'inLanguage' => 'ru-RU',
                    'publisher' => ['@id' => $organizationId],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function product(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $data['name'],
            'description' => (string) $data['description'],
            'url' => (string) $data['url'],
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Theobroma',
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => (string) $data['url'],
                'priceCurrency' => (string) $data['currency'],
                'price' => (string) $data['price'],
                'availability' => !empty($data['in_stock'])
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        $images = array_values(array_filter(array_map('strval', (array) ($data['images'] ?? []))));
        if ($images !== []) {
            $schema['image'] = $images;
        }
        if ((string) ($data['sku'] ?? '') !== '') {
            $schema['sku'] = (string) $data['sku'];
        }

        return $schema;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function article(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) $data['headline'],
            'description' => (string) $data['description'],
            'mainEntityOfPage' => (string) $data['url'],
            'datePublished' => (string) $data['date_published'],
            'dateModified' => (string) $data['date_modified'],
            'author' => [
                '@type' => 'Organization',
                'name' => (string) $data['author'],
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Пища Богов',
            ],
        ];

        if ((string) ($data['image'] ?? '') !== '') {
            $schema['image'] = [(string) $data['image']];
        }
        if ((string) ($data['logo'] ?? '') !== '') {
            $schema['publisher']['logo'] = [
                '@type' => 'ImageObject',
                'url' => (string) $data['logo'],
            ];
        }

        return $schema;
    }
}

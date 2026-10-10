<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class SchemaFactory
{
    /** @param list<array{name:string,url:string}> $items */
    public function collection(string $name, string $description, string $url, array $items): array
    {
        $elements = [];
        foreach ($items as $item) {
            $elements[] = ['@type' => 'ListItem', 'position' => count($elements) + 1,
                'name' => $item['name'], 'url' => $item['url']];
        }
        return ['@context' => 'https://schema.org', '@type' => 'CollectionPage',
            '@id' => $url . '#webpage', 'name' => $name, 'description' => $description, 'url' => $url,
            'inLanguage' => 'ru-RU', 'mainEntity' => ['@type' => 'ItemList',
                'numberOfItems' => count($elements), 'itemListElement' => $elements]];
    }

    public function page(string $name, string $description, string $url): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url . '#webpage',
            'name' => $name, 'description' => $description, 'url' => $url, 'inLanguage' => 'ru-RU'];
    }

    /** @param array<string,mixed> $data */
    public function recipe(array $data): array
    {
        $schema = ['@context' => 'https://schema.org', '@type' => 'Recipe',
            'name' => $data['name'], 'description' => $data['description'], 'url' => $data['url'],
            'image' => [$data['image']], 'author' => ['@type' => 'Organization', 'name' => $data['author']],
            'recipeYield' => '1 кружка', 'recipeIngredient' => array_values($data['ingredients']),
            'recipeInstructions' => array_map(static fn(string $step): array => ['@type' => 'HowToStep', 'text' => $step], $data['steps'])];
        if (!empty($data['minutes'])) $schema['totalTime'] = 'PT' . (int) $data['minutes'] . 'M';
        return $schema;
    }
    /** @param list<array{0:string,1:string}> $questions */
    public function faq(array $questions, string $url): array
    {
        $entities = [];
        foreach ($questions as [$question, $answer]) {
            if (trim($question) !== '' && trim($answer) !== '') {
                $entities[] = ['@type' => 'Question', 'name' => $question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
            }
        }
        return $entities === [] ? [] : ['@context' => 'https://schema.org', '@type' => 'FAQPage',
            '@id' => $url . '#faq', 'url' => $url, 'mainEntity' => $entities];
    }

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
                'name' => (string) ($data['publisher'] ?? 'Пища Богов'),
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

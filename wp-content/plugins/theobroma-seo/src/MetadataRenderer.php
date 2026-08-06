<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class MetadataRenderer
{
    public function render(SeoDocument $document): string
    {
        $lines = [
            $this->meta('name', 'description', $document->description),
            $this->meta('property', 'og:locale', 'ru_RU'),
            $this->meta('property', 'og:type', $document->type),
            $this->meta('property', 'og:title', $document->title),
            $this->meta('property', 'og:description', $document->description),
            $this->meta('property', 'og:url', $document->canonicalUrl),
            $this->meta('property', 'og:site_name', $document->siteName),
            $this->meta('name', 'twitter:card', $document->imageUrl !== '' ? 'summary_large_image' : 'summary'),
            $this->meta('name', 'twitter:title', $document->title),
            $this->meta('name', 'twitter:description', $document->description),
        ];

        if ($document->imageUrl !== '') {
            $lines[] = $this->meta('property', 'og:image', $document->imageUrl);
            $lines[] = $this->meta('name', 'twitter:image', $document->imageUrl);
        }

        if ($document->schema !== []) {
            $json = json_encode(
                $document->schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            if (is_string($json)) {
                $lines[] = '<script type="application/ld+json">' . $json . '</script>';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function meta(string $attribute, string $key, string $value): string
    {
        return sprintf(
            '<meta %s="%s" content="%s">',
            $attribute,
            htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}

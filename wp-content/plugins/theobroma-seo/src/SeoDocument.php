<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final readonly class SeoDocument
{
    /** @param array<string, mixed> $schema */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $type,
        public string $siteName,
        public string $imageUrl = '',
        public array $schema = []
    ) {
    }
}

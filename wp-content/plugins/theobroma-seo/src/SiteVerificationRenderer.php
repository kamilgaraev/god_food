<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class SiteVerificationRenderer
{
    public function sanitize(mixed $value): string
    {
        $token = trim((string) $value);
        return preg_match('/^[A-Za-z0-9_-]{8,128}$/', $token) === 1 ? $token : '';
    }

    public function render(mixed $value): string
    {
        $token = $this->sanitize($value);
        if ($token === '') {
            return '';
        }

        return sprintf(
            '<meta name="yandex-verification" content="%s">' . "\n",
            htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}

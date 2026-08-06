<?php

declare(strict_types=1);

namespace Theobroma\Seo\Tests;

abstract class TestCase
{
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message !== '' ? $message : sprintf(
                "Expected %s, got %s",
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($message !== '' ? $message : sprintf('Missing fragment: %s', $needle));
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException($message !== '' ? $message : sprintf('Unexpected fragment: %s', $needle));
        }
    }
}

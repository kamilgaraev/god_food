<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

abstract class TestCase
{
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message ?: sprintf(
                "Expected %s, got %s",
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertTrue(bool $actual, string $message = ''): void
    {
        $this->assertSame(true, $actual, $message);
    }

    protected function assertThrows(callable $callback, string $exceptionClass): \Throwable
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            if (!$exception instanceof $exceptionClass) {
                throw new \RuntimeException(sprintf('Expected %s, got %s', $exceptionClass, $exception::class));
            }

            return $exception;
        }

        throw new \RuntimeException(sprintf('Expected %s to be thrown', $exceptionClass));
    }
}

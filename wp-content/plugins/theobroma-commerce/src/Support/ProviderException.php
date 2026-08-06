<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Support;

use Theobroma\Commerce\Infrastructure\SecretRedactor;

final class ProviderException extends \RuntimeException
{
    /** @param array<mixed> $context */
    private function __construct(string $message, private readonly int $statusCode, private readonly array $context)
    {
        parent::__construct($message, $statusCode);
    }

    /** @param array<mixed> $context */
    public static function fromResponse(string $message, int $statusCode, array $context = []): self
    {
        return new self($message, $statusCode, (new SecretRedactor())->redact($context));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<mixed> */
    public function context(): array
    {
        return $this->context;
    }
}

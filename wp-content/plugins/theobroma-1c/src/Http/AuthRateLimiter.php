<?php
declare(strict_types=1);

namespace Theobroma\OneC\Http;

final class AuthRateLimiter
{
    private \Closure $read;
    private \Closure $write;
    private \Closure $delete;

    public function __construct(callable $read, callable $write, callable $delete, private readonly int $maximum = 5)
    {
        $this->read = $read(...);
        $this->write = $write(...);
        $this->delete = $delete(...);
    }

    public function allowed(string $bucket): bool
    {
        return ($this->read)($bucket) < $this->maximum;
    }

    public function failure(string $bucket): void
    {
        ($this->write)($bucket, ($this->read)($bucket) + 1);
    }

    public function success(string $bucket): void
    {
        ($this->delete)($bucket);
    }
}

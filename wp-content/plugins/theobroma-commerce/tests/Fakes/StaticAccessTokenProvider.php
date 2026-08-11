<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Integrations\Ozon\AccessTokenProvider;

final class StaticAccessTokenProvider implements AccessTokenProvider
{
    public int $tokenCalls = 0;
    public int $forgetCalls = 0;

    /** @param non-empty-list<string> $tokens */
    public function __construct(private array $tokens)
    {
    }

    public function token(): string
    {
        $index = min($this->tokenCalls, count($this->tokens) - 1);
        $this->tokenCalls++;
        return $this->tokens[$index];
    }

    public function forget(): void
    {
        $this->forgetCalls++;
    }
}

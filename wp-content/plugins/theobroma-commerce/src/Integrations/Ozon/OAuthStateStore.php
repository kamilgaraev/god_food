<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

interface OAuthStateStore
{
    public function issue(int $userId): string;

    public function consume(string $state): ?int;
}


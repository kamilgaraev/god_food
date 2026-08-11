<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

interface AccessTokenProvider
{
    public function token(): string;

    public function forget(): void;
}

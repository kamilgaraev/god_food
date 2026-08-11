<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

use Theobroma\Commerce\Support\ProviderException;

final class OzonAuthorizationGrant
{
    public function __construct(
        private readonly OAuthStateStore $states,
        private readonly OzonAuthenticator $authenticator
    ) {
    }

    public function complete(string $state, string $code, int $userId, string $redirectUri): void
    {
        $initiatorId = $this->states->consume($state);
        if ($initiatorId === null || $initiatorId !== $userId) {
            throw ProviderException::fromResponse('Ozon OAuth state is invalid', 403);
        }

        $this->authenticator->authorize($code, $redirectUri);
    }
}


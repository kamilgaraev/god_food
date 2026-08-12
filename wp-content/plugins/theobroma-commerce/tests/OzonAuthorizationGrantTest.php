<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonAuthenticator;
use Theobroma\Commerce\Integrations\Ozon\OzonAuthorizationGrant;
use Theobroma\Commerce\Support\ProviderException;
use Theobroma\Commerce\Tests\Fakes\MemoryOAuthStateStore;
use Theobroma\Commerce\Tests\Fakes\MemoryOzonTokenStore;
use Theobroma\Commerce\Tests\Fakes\RecordingTransport;

final class OzonAuthorizationGrantTest extends TestCase
{
    public function testConsumesValidStateWithoutCurrentWordPressUserAndReturnsInitiator(): void
    {
        $states = new MemoryOAuthStateStore();
        $state = $states->issue(42);
        $tokens = new MemoryOzonTokenStore();
        $transport = new RecordingTransport([[
            'status' => 200,
            'body' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ],
        ]]);
        $grant = new OzonAuthorizationGrant(
            $states,
            new OzonAuthenticator($transport, $tokens, 'client-id', 'client-secret')
        );

        $initiatorId = $grant->complete($state, 'authorization-code', 'https://shop.test/ozon/callback');

        $this->assertSame(42, $initiatorId);
        $this->assertSame('access-token', $tokens->value['token'] ?? null);
        $this->assertSame(null, $states->consume($state));
    }

    public function testRejectsReplayedState(): void
    {
        $states = new MemoryOAuthStateStore();
        $state = $states->issue(42);
        $transport = new RecordingTransport([[
            'status' => 200,
            'body' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ],
        ]]);
        $grant = new OzonAuthorizationGrant(
            $states,
            new OzonAuthenticator($transport, new MemoryOzonTokenStore(), 'client-id', 'client-secret')
        );
        $grant->complete($state, 'authorization-code', 'https://shop.test/ozon/callback');

        $this->assertThrows(
            static fn () => $grant->complete($state, 'replayed-code', 'https://shop.test/ozon/callback'),
            ProviderException::class
        );

        $this->assertSame(1, count($transport->requests));
    }
}


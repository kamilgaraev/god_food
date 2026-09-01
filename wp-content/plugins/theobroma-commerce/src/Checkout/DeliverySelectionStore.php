<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliverySelectionStore
{
    private const KEY = 'theobroma_delivery_selection_v1';

    /** @var callable(string):mixed */
    private $getter;

    /** @var callable(string,mixed):void */
    private $setter;

    /** @var callable(string):void */
    private $deleter;

    /** @var callable(string,array<string,mixed>):void */
    private $logger;

    public function __construct(?callable $getter = null, ?callable $setter = null, ?callable $deleter = null, ?callable $logger = null)
    {
        $this->getter = $getter ?? static fn (string $key): mixed => function_exists('WC') && WC()->session ? WC()->session->get($key) : null;
        $this->setter = $setter ?? static function (string $key, mixed $value): void {
            if (function_exists('WC') && WC()->session) {
                WC()->session->set($key, $value);
            }
        };
        $this->deleter = $deleter ?? static function (string $key): void {
            if (function_exists('WC') && WC()->session) {
                WC()->session->__unset($key);
            }
        };
        $this->logger = $logger ?? static function (string $message, array $context): void {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info($message, array_merge(['source' => 'theobroma-delivery-selection'], $context));
            }
        };
    }

    public function load(): ?DeliverySelection
    {
        $value = ($this->getter)(self::KEY);
        return is_array($value) ? DeliverySelection::fromArray($value) : null;
    }

    public function save(DeliverySelection $selection): void
    {
        ($this->setter)(self::KEY, $selection->toArray());
        ($this->logger)('Delivery selection saved', [
            'provider' => $selection->provider(),
            'fingerprint' => $selection->fingerprint(),
            'confirmed' => $selection->isConfirmed(),
        ]);
    }

    public function clear(): void
    {
        ($this->deleter)(self::KEY);
    }

    public function confirmedFor(string $provider, string $fingerprint): ?DeliverySelection
    {
        $selection = $this->load();
        if (!$selection instanceof DeliverySelection) {
            ($this->logger)('Delivery selection unavailable', [
                'reason' => 'missing',
                'requested_provider' => $provider,
                'requested_fingerprint' => $fingerprint,
            ]);
            return null;
        }
        if ($selection->provider() !== $provider) {
            ($this->logger)('Delivery selection unavailable', [
                'reason' => 'provider_mismatch',
                'requested_provider' => $provider,
                'saved_provider' => $selection->provider(),
                'requested_fingerprint' => $fingerprint,
                'saved_fingerprint' => $selection->fingerprint(),
            ]);
            return null;
        }
        if ($selection->fingerprint() !== $fingerprint) {
            ($this->logger)('Delivery selection unavailable', [
                'reason' => 'fingerprint_mismatch',
                'requested_provider' => $provider,
                'requested_fingerprint' => $fingerprint,
                'saved_fingerprint' => $selection->fingerprint(),
            ]);
            $this->clear();
            return null;
        }
        if (!$selection->isConfirmed()) {
            ($this->logger)('Delivery selection unavailable', [
                'reason' => 'invalid',
                'requested_provider' => $provider,
                'requested_fingerprint' => $fingerprint,
            ]);
            $this->clear();
            return null;
        }
        ($this->logger)('Delivery selection confirmed', [
            'provider' => $provider,
            'fingerprint' => $fingerprint,
        ]);
        return $selection;
    }
}

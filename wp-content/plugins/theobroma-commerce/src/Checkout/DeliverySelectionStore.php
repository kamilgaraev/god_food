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

    public function __construct(?callable $getter = null, ?callable $setter = null, ?callable $deleter = null)
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
    }

    public function load(): ?DeliverySelection
    {
        $value = ($this->getter)(self::KEY);
        return is_array($value) ? DeliverySelection::fromArray($value) : null;
    }

    public function save(DeliverySelection $selection): void
    {
        ($this->setter)(self::KEY, $selection->toArray());
    }

    public function clear(): void
    {
        ($this->deleter)(self::KEY);
    }

    public function confirmedFor(string $provider, string $fingerprint): ?DeliverySelection
    {
        $selection = $this->load();
        if (!$selection instanceof DeliverySelection) {
            return null;
        }
        if ($selection->provider() !== $provider) {
            return null;
        }
        if ($selection->fingerprint() !== $fingerprint || !$selection->isConfirmed()) {
            $this->clear();
            return null;
        }
        return $selection;
    }
}

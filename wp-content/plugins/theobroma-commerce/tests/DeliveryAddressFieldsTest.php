<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliveryAddressFields;

final class DeliveryAddressFieldsTest extends TestCase
{
    public function testRestoresAddressFieldsRemovedByThemeWithoutMakingPickupRequireThem(): void
    {
        $fields = (new DeliveryAddressFields())->fields(['billing' => [
            'billing_city' => ['required' => true],
        ]]);

        $this->assertTrue(isset($fields['billing']['billing_postcode']));
        $this->assertTrue(isset($fields['billing']['billing_address_1']));
        $this->assertTrue(isset($fields['billing']['billing_address_2']));
        $this->assertSame(false, $fields['billing']['billing_postcode']['required']);
        $this->assertSame(false, $fields['billing']['billing_address_1']['required']);
    }
}

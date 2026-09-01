<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Checkout\DeliveryAddressFields;

final class DeliveryAddressFieldsTest extends TestCase
{
    public function testRestoresAddressFieldsRemovedByThemeWithoutMakingPickupRequireThem(): void
    {
        $fields = (new DeliveryAddressFields())->fields(['billing' => [
            'billing_city' => ['required' => true, 'priority' => 10],
            'billing_first_name' => ['priority' => 20],
            'billing_phone' => ['priority' => 30],
            'billing_email' => ['priority' => 40],
        ]]);

        $this->assertTrue(isset($fields['billing']['billing_postcode']));
        $this->assertTrue(isset($fields['billing']['billing_country']));
        $this->assertTrue(isset($fields['billing']['billing_address_1']));
        $this->assertTrue(isset($fields['billing']['billing_address_2']));
        $this->assertSame(false, $fields['billing']['billing_postcode']['required']);
        $this->assertSame(false, $fields['billing']['billing_address_1']['required']);
        $this->assertSame('hidden', $fields['billing']['billing_country']['type']);
        $this->assertSame('RU', $fields['billing']['billing_country']['default']);
        $this->assertSame(10, $fields['billing']['billing_first_name']['priority']);
        $this->assertSame(20, $fields['billing']['billing_phone']['priority']);
        $this->assertSame(30, $fields['billing']['billing_email']['priority']);
        $this->assertSame(40, $fields['billing']['billing_city']['priority']);
        $this->assertSame(50, $fields['billing']['billing_address_1']['priority']);
        $this->assertSame(60, $fields['billing']['billing_postcode']['priority']);
        $this->assertSame(70, $fields['billing']['billing_address_2']['priority']);
        $this->assertTrue(in_array('theobroma-delivery-address', $fields['billing']['billing_address_1']['class'], true));
    }
}

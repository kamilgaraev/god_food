<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Integrations\Ozon\OzonCapabilities;

final class OzonCapabilitiesTest extends TestCase
{
    public function testFailsClosedUntilApprovalCredentialsMappingAndLiveTestExist(): void
    {
        $this->assertSame('awaiting_approval', (new OzonCapabilities(false, false, false, false))->status());
        $this->assertSame('credentials_missing', (new OzonCapabilities(true, false, false, false))->status());
        $this->assertSame('products_unmapped', (new OzonCapabilities(true, true, false, false))->status());
        $this->assertSame('live_test_required', (new OzonCapabilities(true, true, true, false))->status());
        $this->assertSame('ready', (new OzonCapabilities(true, true, true, true))->status());
        $this->assertTrue(!(new OzonCapabilities(true, true, true, false))->canOfferDelivery());
        $this->assertTrue((new OzonCapabilities(true, true, true, true))->canOfferDelivery());
    }
}

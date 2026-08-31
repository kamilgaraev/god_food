<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Rest\DeliveryCheckoutController;

final class DeliveryCheckoutControllerTest extends TestCase
{
    public function testAllowsPublicCheckoutRequestsWithoutExpiringPageNonce(): void
    {
        $controller = new DeliveryCheckoutController();

        $this->assertTrue($controller->publicAccess());
    }
}

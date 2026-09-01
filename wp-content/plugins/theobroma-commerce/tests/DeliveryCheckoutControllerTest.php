<?php

declare(strict_types=1);

namespace {
    if (!function_exists('add_action')) {
        function add_action(string $hook, callable $callback): void
        {
            $GLOBALS['theobroma_delivery_actions'][$hook][] = $callback;
        }
    }
}

namespace Theobroma\Commerce\Tests {
    use Theobroma\Commerce\Rest\DeliveryCheckoutController;

    final class DeliveryCheckoutControllerTest extends TestCase
    {
        public function testAllowsPublicCheckoutRequestsWithoutExpiringPageNonce(): void
        {
            $controller = new DeliveryCheckoutController();

            $this->assertTrue($controller->publicAccess());
        }

        public function testRegistersQuoteInsideWooAjaxCartLifecycle(): void
        {
            $GLOBALS['theobroma_delivery_actions'] = [];
            $controller = new DeliveryCheckoutController();

            $controller->register();

            $callbacks = $GLOBALS['theobroma_delivery_actions']['wc_ajax_theobroma_delivery_quote'] ?? [];
            $this->assertSame(1, count($callbacks));
            $this->assertSame($controller, $callbacks[0][0]);
            $this->assertSame('ajaxQuote', $callbacks[0][1]);
        }
    }
}

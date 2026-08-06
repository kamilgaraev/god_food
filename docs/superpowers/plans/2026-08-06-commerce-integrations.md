# Commerce Integrations Implementation Plan

> **For Codex:** execute this plan test-first and verify each provider independently.

**Goal:** Add production-grade WooCommerce delivery integration boundaries, fully implement CDEK API v2 without credentials, and implement the documented Ozon Logistics private Seller API application flow while keeping it fail-closed until Ozon approval and live testing.

**Architecture:** A new `theobroma-commerce` plugin owns HTTP/provider/shipping/order logic. The theme only renders the existing checkout modal. Provider clients normalize external responses; WooCommerce adapters consume normalized rates and shipment records.

**Tech Stack:** WordPress 7, WooCommerce 10, PHP 8.3, WordPress HTTP API, Action Scheduler/WP-Cron, plain PHP contract tests, Playwright smoke tests.

---

### Task 1: Provider-independent HTTP and result contracts

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Contracts/Transport.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Support/ProviderException.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Infrastructure/WpTransport.php`
- Test: `wp-content/plugins/theobroma-commerce/tests/ProviderContractsTest.php`

Write failing tests for bounded timeout, JSON normalization and secret redaction; implement minimal contracts; rerun tests.

### Task 2: CDEK API v2 client

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Cdek/CdekClient.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Cdek/CdekTokenStore.php`
- Test: `wp-content/plugins/theobroma-commerce/tests/CdekClientTest.php`

Test OAuth, tariff-list, delivery-points, create/get order and idempotency headers using a recording transport. Implement token caching and strict response validation.

### Task 3: WooCommerce CDEK shipping and checkout data

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Shipping/CdekShippingMethod.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/PickupPointFields.php`
- Create: `wp-content/plugins/theobroma-commerce/assets/js/checkout.js`
- Test: `wp-content/plugins/theobroma-commerce/tests/CdekShippingMethodTest.php`

Test package normalization, fail-closed behavior and pickup validation before registering separate courier/pickup rates.

### Task 4: Shipment lifecycle and webhooks

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Orders/CdekShipmentService.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Rest/CdekWebhookController.php`
- Test: `wp-content/plugins/theobroma-commerce/tests/CdekShipmentServiceTest.php`

Test duplicate-hook idempotency and documented status mapping. Persist external IDs through WooCommerce CRUD and add sanitized order notes.

### Task 5: Ozon Logistics private application

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonCapabilities.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonClient.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Orders/OzonOrderService.php`
- Test: `wp-content/plugins/theobroma-commerce/tests/OzonCapabilitiesTest.php`

Test OAuth/scopes, buyer delivery check, pickup map/list/info, checkout splits, paid-only order creation, asynchronous cancellation and fail-closed activation. Ozon remains hidden until real end-to-end testing because the provider supplies no sandbox.

### Task 6: Admin settings and operational diagnostics

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Admin/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-commerce/theobroma-commerce.php`
- Modify: `compose.yaml`
- Test: `wp-content/plugins/theobroma-commerce/tests/SettingsTest.php`

Add masked settings, connection tests, webhook URLs, capability notices and HPOS declaration. Mount and activate plugin reproducibly.

### Task 7: Runtime verification and handoff

Run all contract tests, PHP lint, WordPress activation smoke, WooCommerce shipping registration, checkout browser checks at desktop/tablet/mobile, and confirm no provider appears with incomplete configuration. Document the exact CDEK/Ozon/YooKassa external onboarding steps and callbacks.

# Ozon and CDEK Checkout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver complete Ozon and CDEK pickup/courier checkout with optional Yandex maps, list fallback, live quotes, and correct shipment creation timing.

**Architecture:** A provider-neutral WooCommerce session store and REST controller coordinate provider adapters. Shipping methods consume only server-confirmed quotes; a progressively enhanced selector renders lists by default and a Yandex map when configured.

**Tech Stack:** PHP 8+, WordPress, WooCommerce classic checkout, vanilla JavaScript/jQuery, Yandex Maps JavaScript API, Ozon Seller API, CDEK API v2.

**Spec:** `docs/superpowers/specs/2026-08-31-ozon-cdek-checkout-design.md`

## Global Constraints

- Work on `codex/ozon-cdek-checkout`, based on current `origin/main`.
- Never expose provider secrets or the Yandex HTTP Geocoder key to JavaScript.
- Never create a rate from client-provided price or payload data.
- Lists remain usable when map keys are missing or map loading fails.
- Ozon remains fail-closed without OAuth, complete SKU mapping, and a live confirmed quote.

---

### Task 1: Yandex map settings and diagnostics

**Files:**
- Modify: `wp-content/plugins/theobroma-commerce/src/Admin/Settings.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Admin/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Admin/YandexMapsConnectionAction.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Admin/YandexMapsConnectionChecker.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Plugin.php`
- Test: `wp-content/plugins/theobroma-commerce/tests/SettingsTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/YandexMapsConnectionCheckerTest.php`

**Interfaces:**
- Produces settings keys `yandex_maps_js_key` and `yandex_geocoder_key`.
- Produces `YandexMapsConnectionChecker::check(string $jsKey, string $geocoderKey): array` with independent normalized statuses.

- [ ] Write failing tests for defaults, secret preservation, sanitization, missing keys, valid geocoder response, and HTTP 403.
- [ ] Run the focused tests and confirm they fail because the settings/checker do not exist.
- [ ] Implement the minimal settings fields, authenticated diagnostic action, checker, and admin result rendering.
- [ ] Run focused tests and the full commerce PHP suite.
- [ ] Commit the task.

### Task 2: Provider-neutral checkout selection state

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliverySelection.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliverySelectionStore.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliveryFingerprint.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/DeliverySelectionStoreTest.php`

**Interfaces:**
- Produces immutable normalized selection arrays with provider, kind, destination/cart fingerprint, point snapshot, quote, and create payload.
- Produces `load()`, `save()`, `clear()`, and `confirmedFor(string $provider, string $fingerprint)` operations.

- [ ] Write failing tests for normalization, save/load, invalidation on fingerprint mismatch, and confirmed quote lookup.
- [ ] Run the focused tests and confirm expected failures.
- [ ] Implement the session adapter with injected callbacks for unit testing.
- [ ] Run focused and full PHP tests.
- [ ] Commit the task.

### Task 3: CDEK and Ozon checkout services

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/CdekCheckoutService.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/OzonCheckoutService.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/CheckoutProductLines.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliveryQuote.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/CdekCheckoutServiceTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/OzonCheckoutServiceTest.php`

**Interfaces:**
- Consumes existing `CdekClient`, `CdekPackageBuilder`, `OzonClient`, and product metadata.
- Produces normalized point lists and `DeliveryQuote` values containing label, cost, selection snapshot, and provider create payload.

- [ ] Write failing tests from official request/response fixtures for point normalization and pickup/courier quotes.
- [ ] Confirm tests fail for missing services.
- [ ] Implement minimal adapters and response validation without accepting client prices.
- [ ] Run focused and full PHP tests.
- [ ] Commit the task.

### Task 4: Checkout REST API and shipping rates

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Rest/DeliveryCheckoutController.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Shipping/OzonShippingMethod.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Shipping/CdekShippingMethod.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/DeliveryCheckoutControllerTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/DeliveryShippingQuoteTest.php`

**Interfaces:**
- REST routes return normalized public point/quote data and store server-confirmed selections.
- Shipping methods read matching confirmed quotes from `DeliverySelectionStore`; Ozon exposes only a guarded bootstrap rate before confirmation.

- [ ] Write failing tests for nonce/selection validation, stale fingerprints, bootstrap gating, and confirmed rate metadata.
- [ ] Confirm the focused tests fail for missing routes and behavior.
- [ ] Implement the controller and quote-backed shipping methods.
- [ ] Run focused and full PHP tests.
- [ ] Commit the task.

### Task 5: Unified progressive checkout selector

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliverySelector.php`
- Replace: `wp-content/plugins/theobroma-commerce/assets/js/checkout.js`
- Create: `wp-content/plugins/theobroma-commerce/assets/css/checkout-delivery.css`
- Modify: `wp-content/plugins/theobroma-commerce/src/Checkout/PickupPointFields.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliveryAddressFields.php`
- Create: `scripts/verify-delivery-selector.spec.js`

**Interfaces:**
- Consumes localized REST URLs, nonce, public JS map key, and current selection.
- Produces provider tabs, pickup/courier controls, searchable point list, optional map markers, inline errors, and checkout refresh events.

- [ ] Write a failing static/browser contract test for fallback list markup, map enhancement hooks, selection restore, and no localized secret.
- [ ] Confirm the test fails against the current checkout asset.
- [ ] Implement semantic markup, state-preserving JavaScript, and responsive styles.
- [ ] Run the selector test and existing cart/checkout UI tests.
- [ ] Commit the task.

### Task 6: Order persistence and COD/prepaid lifecycle

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Checkout/DeliveryOrderMeta.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Orders/OzonOrderLifecycle.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Orders/CdekOrderLifecycle.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/DeliveryOrderMetaTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/ShipmentLifecycleTest.php`

**Interfaces:**
- Copies confirmed session state to order/shipping-item metadata.
- Dispatches COD on checkout completion and prepaid shipments on paid/processing transition, guarded by existing external IDs.

- [ ] Write failing tests for metadata copying, COD immediate dispatch, prepaid deferral, and duplicate hook idempotency.
- [ ] Confirm expected failures.
- [ ] Implement persistence and lifecycle guards.
- [ ] Run focused and full PHP tests.
- [ ] Commit the task.

### Task 7: Documentation and verification

**Files:**
- Modify: `docs/integrations-setup.md`
- Modify: `docs/acceptance-status.md`

**Interfaces:**
- Documents Yandex keys, fallback behavior, provider setup, COD behavior, and live acceptance limits.

- [ ] Update operator setup and acceptance evidence.
- [ ] Run PHP syntax checks for all changed PHP files.
- [ ] Run `php wp-content/plugins/theobroma-commerce/tests/run.php`.
- [ ] Run delivery-selector and existing checkout UI tests.
- [ ] Review the final diff for secrets, stale placeholders, and unrelated changes.
- [ ] Commit verification documentation.


# Ozon Client Credentials Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the manually supplied Ozon Bearer token and readiness flags with automatic OAuth authentication from `Client ID` and `Secret`, while hiding Ozon for carts that contain any item without an Ozon SKU.

**Architecture:** A focused token provider owns OAuth and caching, while `OzonClient` owns authenticated Seller API requests and a single retry after `401`. Settings resolve database values or environment constants, and a cart eligibility service gates the WooCommerce shipping method before any provider request.

**Tech Stack:** PHP 8.1+, WordPress Settings API/transients/admin-post actions, WooCommerce shipping APIs, custom zero-dependency PHP test runner.

## Global Constraints

- Work only on `codex/ozon-client-credentials`, never directly on `main`.
- Store no access token or secret in rendered HTML, exceptions, or logs.
- `THEOBROMA_OZON_CLIENT_ID` and `THEOBROMA_OZON_CLIENT_SECRET` override database settings.
- Ozon is unavailable for the whole cart when any product or variation lacks an Ozon SKU.
- Keep the existing confirmed-quote boundary; credentials and SKU presence must never fabricate a tariff.
- Do not add package dependencies, run migrations, or start a development server.

---

### Task 1: OAuth token provider and cache

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/AccessTokenProvider.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/TokenStore.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/WordPressTokenStore.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonAuthenticator.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/Fakes/MemoryOzonTokenStore.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/OzonAuthenticatorTest.php`

**Interfaces:**
- Produces: `AccessTokenProvider::token(): string` and `AccessTokenProvider::forget(): void`.
- Produces: `TokenStore::get(): ?array`, `put(string $token, int $expiresAt): void`, and `forget(): void`.
- `OzonAuthenticator` consumes `Transport`, `TokenStore`, client ID, client secret, and base URL.

- [ ] **Step 1: Write failing authentication tests**

Cover a form-encoded `POST /oauth/token` with `grant_type=client_credentials`, cache reuse, early expiry, missing credentials, and invalid provider responses.

- [ ] **Step 2: Run the suite and confirm the new class is missing**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: failure loading `OzonAuthenticator`.

- [ ] **Step 3: Implement token contracts, transient store, and authenticator**

Use this public shape:

```php
final class OzonAuthenticator implements AccessTokenProvider
{
    public function __construct(
        Transport $transport,
        TokenStore $tokens,
        string $clientId,
        string $clientSecret,
        string $baseUrl = 'https://api-seller.ozon.ru'
    );

    public function token(): string;
    public function forget(): void;
}
```

Send credentials in the request body, accept only a non-empty string `access_token`, cache using `expires_in`, and refresh when fewer than 60 seconds remain.

- [ ] **Step 4: Run the focused and full PHP suites**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: all tests pass.

- [ ] **Step 5: Commit the authentication slice**

Commit: `feat: add automatic Ozon authentication`

### Task 2: Authenticated Ozon client with one retry

**Files:**
- Modify: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonClient.php`
- Modify: `wp-content/plugins/theobroma-commerce/tests/OzonClientTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/Fakes/StaticAccessTokenProvider.php`

**Interfaces:**
- Consumes: `AccessTokenProvider::token()` and `forget()` from Task 1.
- Produces: existing Ozon API methods with automatic Bearer headers and one retry after `401`.

- [ ] **Step 1: Change client tests to inject a token provider**

Assert cached token use, `forget()` plus a second token lookup after the first `401`, no retry for other status codes, and no credential values in `ProviderException` context.

- [ ] **Step 2: Run tests and confirm constructor/retry failures**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: Ozon client tests fail until the constructor and request loop change.

- [ ] **Step 3: Refactor `OzonClient` minimally**

Replace the string token constructor argument with `AccessTokenProvider`. Make `post()` perform at most two attempts, invalidating the provider only after the first `401`. Preserve existing endpoint and response normalization behavior.

- [ ] **Step 4: Run all PHP tests**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: all tests pass.

- [ ] **Step 5: Commit the client slice**

Commit: `refactor: authenticate Ozon client automatically`

### Task 3: Credential settings and connection check

**Files:**
- Modify: `wp-content/plugins/theobroma-commerce/src/Admin/Settings.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Admin/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonClientFactory.php`
- Create: `wp-content/plugins/theobroma-commerce/src/Admin/OzonConnectionAction.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Plugin.php`
- Modify: `wp-content/plugins/theobroma-commerce/tests/SettingsTest.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/OzonClientFactoryTest.php`

**Interfaces:**
- Produces: settings keys `ozon_client_id` and `ozon_client_secret`.
- Produces: `OzonClientFactory::fromSettings(array $settings): OzonClient` and `authenticatorFromSettings(array $settings): AccessTokenProvider`.
- Consumes: token provider and client contracts from Tasks 1-2.

- [ ] **Step 1: Write failing settings and factory tests**

Assert trimming client ID, preserving an existing secret on blank input, removing legacy Ozon readiness/token keys, and constructing credentials from settings. Constant overrides remain a runtime branch covered by a small isolated smoke assertion where safe.

- [ ] **Step 2: Run tests and verify old settings behavior fails**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: settings/factory tests fail.

- [ ] **Step 3: Implement settings, factory, and explicit connection action**

Render only Client ID, Secret, SKU audit, and a nonce-protected «Проверить подключение» action. The action requires `manage_woocommerce`, requests a token, stores a short-lived success/error notice without provider bodies, and redirects back to the settings page. Clear the Ozon token transient when saved credentials change.

- [ ] **Step 4: Run PHP tests and syntax checks**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`

Run: `Get-ChildItem wp-content/plugins/theobroma-commerce/src,wp-content/plugins/theobroma-commerce/tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

Expected: all tests pass and every file reports no syntax errors.

- [ ] **Step 5: Commit the settings slice**

Commit: `feat: configure Ozon with client credentials`

### Task 4: Per-cart SKU eligibility and runtime wiring

**Files:**
- Create: `wp-content/plugins/theobroma-commerce/src/Shipping/OzonCartEligibility.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Shipping/OzonShippingMethod.php`
- Modify: `wp-content/plugins/theobroma-commerce/src/Orders/OzonOrderLifecycle.php`
- Create: `wp-content/plugins/theobroma-commerce/tests/OzonCartEligibilityTest.php`
- Remove: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonCapabilities.php`
- Remove: `wp-content/plugins/theobroma-commerce/src/Integrations/Ozon/OzonReadinessFactory.php`
- Remove: `wp-content/plugins/theobroma-commerce/tests/OzonCapabilitiesTest.php`
- Remove: `wp-content/plugins/theobroma-commerce/tests/OzonReadinessFactoryTest.php`
- Modify: `wp-content/plugins/theobroma-commerce/tests/wordpress-smoke.php`

**Interfaces:**
- Produces: `OzonCartEligibility::allItemsMapped(array $package): bool`.
- Consumes: `OzonClientFactory` from Task 3.

- [ ] **Step 1: Write failing cart eligibility tests**

Cover all mapped products, one missing product, variation-owned SKU, variation fallback to parent SKU, empty/malformed contents, and quantity-independent behavior.

- [ ] **Step 2: Run tests and confirm the eligibility service is missing**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`
Expected: failure loading `OzonCartEligibility`.

- [ ] **Step 3: Implement eligibility and replace readiness wiring**

Gate `calculate_shipping()` before invoking the confirmed-quote filter. Build authenticated Ozon clients through the factory in order creation. Catch provider failures at WooCommerce boundaries, omit the Ozon rate, and log only a safe stage/status message. Delete the obsolete global readiness classes and tests.

- [ ] **Step 4: Run all plugin checks**

Run: `php wp-content/plugins/theobroma-commerce/tests/run.php`

Run: `php wp-content/plugins/theobroma-commerce/tests/wordpress-smoke.php`

Run: `php wp-content/plugins/theobroma-commerce/tests/order-hooks-smoke.php`

Run: `Get-ChildItem wp-content/plugins/theobroma-commerce/src,wp-content/plugins/theobroma-commerce/tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

Expected: unit tests and smoke tests pass; all PHP files are syntactically valid.

- [ ] **Step 5: Update setup documentation and commit**

Update `docs/integrations-setup.md` and `docs/integration-acceptance.md` to describe Client ID/Secret, automatic token handling, all-items SKU gating, and staging verification.

Commit: `feat: gate Ozon delivery by cart SKU mapping`

### Task 5: Final verification

**Files:**
- Review all files changed since `origin/main`.

- [ ] **Step 1: Run whitespace and secret scans**

Run: `git diff --check origin/main...HEAD`

Run: `rg -n "ozon_access_token|ozon_approved|ozon_products_mapped|ozon_live_test_completed|private-oauth-token" wp-content/plugins/theobroma-commerce docs/integrations-setup.md docs/integration-acceptance.md`

Expected: no production references to removed settings and no real credential values.

- [ ] **Step 2: Run the complete relevant test set again**

Run the unit suite, WordPress smoke, order-hook smoke, and PHP lint commands from Task 4.

- [ ] **Step 3: Review the final diff against the design**

Confirm automatic credentials, cache behavior, single `401` retry, settings migration, all-cart SKU gating, safe failures, and documentation are present; confirm unrelated files remain untouched.

- [ ] **Step 4: Commit any final corrections**

Commit only if verification required code or documentation corrections, using `fix: complete Ozon credential migration`.

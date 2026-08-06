# Theobroma Commerce Integrations Design

## Scope

Implement the commerce requirements from the migration specification without putting business logic into the theme. AI-page work is explicitly excluded. The first integration slice covers CDEK delivery and the Ozon Pay + Ozon Delivery onboarding boundary, while preserving WooCommerce orders, HPOS compatibility, checkout modals, and future 1C/loyalty work.

## Constraints confirmed by official providers

- CDEK exposes a public API v2: OAuth client credentials, tariff calculation, delivery points, orders, statuses, and webhooks.
- Ozon Delivery for an external internet shop is exposed through a private Seller API application after the seller enables the Ozon Delivery application and passes review. It requires a Russian seller working with FBO and/or FBS and selling both on Ozon and the external shop.
- No mock tariffs, fake pickup points, or invented Ozon endpoints may be shown to customers.

## Architecture

Create a dedicated `theobroma-commerce` plugin. The theme remains responsible only for presentation.

### Layers

1. `Infrastructure/Http`: bounded HTTP client, timeouts, JSON validation, redacted error logging, retry classification.
2. `Integrations/Cdek`: OAuth token cache, tariff/delivery-point/order/webhook clients, DTO-style normalized results.
3. `Integrations/Ozon`: OAuth private-application client for the Ozon Logistics Seller API scopes. Until approval and credentials are loaded, it reports `not_configured` and cannot offer a rate.
4. `Shipping`: WooCommerce shipping methods and checkout field validation. CDEK courier and pickup are separate selectable rates; pickup requires a selected office code.
5. `Orders`: idempotent shipment creation after the configured paid/processing transition, external IDs in order meta, status synchronization and order notes.
6. `Admin`: settings/status screen, sandbox/live modes, masked credentials, connection diagnostics, webhook URLs and operational errors.

## CDEK flow

1. Resolve sender and destination from WooCommerce package/customer data.
2. Obtain and transient-cache OAuth token; never expose it to browser JavaScript.
3. Request tariff list using real package weight/dimensions and select enabled tariffs by delivery mode.
4. For pickup delivery, load actual offices for the destination and require the buyer to choose one.
5. Save tariff code, office code, quoted cost/time and destination snapshot to the order.
6. Create the CDEK order idempotently when the WooCommerce order enters the configured eligible status.
7. Consume signed/validated status callbacks where the provider supports verification; otherwise re-read the shipment from CDEK before mutating WooCommerce state.
8. Store sanitized diagnostics and append human-readable order notes.

## Ozon flow

1. Show the provider only after the private application is approved, OAuth succeeds, product/stock mapping is complete, and a real capability check succeeds.
2. Verify the buyer phone through `v1/delivery/check`; expose pickup via `v1/delivery/map` or the cached `v1/delivery/point/list` + `v1/delivery/point/info` data.
3. Call `v2/delivery/checkout` with mapped products and pickup/courier destination to obtain availability, dates and Ozon shipment splits.
4. Create the Ozon order with `v2/order/create` only after payment confirmation. A HTTP 200 alone is not treated as proof that the order was created.
5. Persist the Ozon order/posting IDs, delivery scheme and selected pickup/address snapshot in WooCommerce order meta.
6. Support documented order/posting cancellation checks, asynchronous cancellation status, FBO/FBS posting reads, returns and stock diagnostics.
7. Until approval, credentials and a real end-to-end test are complete, admin diagnostics show `Awaiting merchant activation` and checkout does not expose Ozon Delivery. Ozon has no sandbox for this feature.

## Security and reliability

- Capability checks and nonces for admin actions; secrets are never localized into frontend scripts or logged.
- `wp_safe_remote_*`/WooCommerce APIs, strict timeouts, bounded retries, transient caching and no API call on every page render.
- Idempotency keys for shipment creation; repeated hooks or callbacks must not duplicate shipments.
- HPOS compatibility declaration and CRUD-based order access.
- External failure never corrupts the order. It creates a note/admin alert and schedules a bounded retry.

## Acceptance evidence

- Unit-style PHP tests for request construction, normalization, idempotency, status mapping, and secret redaction.
- Local WordPress smoke test proves plugin activation, HPOS declaration, settings, shipping method registration, and no fatal errors.
- CDEK sandbox contract test proves OAuth and a real tariff response once test credentials are supplied.
- Browser checkout tests prove courier/pickup selection and validation on desktop/tablet/mobile.
- Ozon is accepted only after an end-to-end test with the merchant package issued by Ozon; an unconfigured status is not counted as completion.

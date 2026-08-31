# Ozon and CDEK Checkout Design

## Goal

Complete the customer-facing Ozon Delivery and CDEK checkout flow: pickup and courier selection, live quotes, optional maps, validated map settings, order persistence, and provider order creation for both prepaid and cash-on-delivery orders.

## Confirmed product decisions

- Ozon and CDEK use one checkout selector and one persisted selection model.
- Pickup is selectable on a Yandex map when valid keys are configured.
- Without map keys, pickup remains fully usable through a searchable list.
- Courier delivery uses the checkout address and a provider-confirmed quote.
- Cash-on-delivery shipments are created immediately after checkout; online-payment shipments are created only after payment succeeds.
- Provider credentials and server-side geocoder keys never reach browser JavaScript.
- Ozon remains fail-closed when OAuth, SKU mapping, or a confirmed live quote is missing.

## Architecture

### Settings and diagnostics

Extend the existing commerce settings with a Yandex JavaScript API key and HTTP Geocoder key. The JavaScript key is localized only on checkout and is expected to be domain-restricted. The geocoder key is treated as a secret, masked in admin, and used only server-side.

An authenticated admin action validates both configured keys independently. The HTTP Geocoder key is checked with a bounded geocoding request and explicit handling of HTTP 403. The JavaScript key is checked by loading the Yandex API in an isolated admin diagnostic element; the browser posts the result back to the authenticated action. The status distinguishes `valid`, `invalid`, and `not_configured`; a missing key is not an error because list fallback remains supported.

### Checkout state

`DeliverySelectionStore` owns a versioned, provider-neutral selection in WooCommerce session. It stores provider, kind (`pickup` or `courier`), pickup identifier and snapshot, destination fingerprint, normalized quote, and provider create payload. Changes to cart contents, destination, provider, or pickup point invalidate the quote.

REST endpoints expose:

- normalized pickup points for Ozon and CDEK;
- Ozon map clusters for the current viewport;
- quote confirmation for pickup or courier;
- current selection and selection clearing.

All endpoints validate a WooCommerce Store API nonce, derive cart products and prices on the server, and never accept a client-supplied shipping price or create payload.

### Provider adapters

`CdekCheckoutService` reuses `CdekClient`, `CdekPackageBuilder`, and tariff selection. It returns normalized pickup points and confirmed pickup/courier quotes. The selected CDEK office code is included in the session snapshot.

`OzonCheckoutService` builds product lines from the live cart using `_theobroma_ozon_sku`, verifies buyer delivery eligibility, resolves pickup details or courier coordinates, calls `v2/delivery/checkout`, selects available splits/timeslots, and returns a normalized quote plus the exact create payload required by `v2/order/create`.

When the geocoder key is absent, pickup works from provider coordinates and lists. Courier methods that require coordinates are shown only when coordinates can be derived from a previously selected map position or a successful configured geocoder request; the UI explains why courier is unavailable instead of inventing a quote.

### Shipping methods and UI

The classic WooCommerce checkout renders a single delivery selector beside provider rates. A provisional zero-cost Ozon bootstrap rate is allowed only to open the selector; checkout validation prevents order placement until a confirmed quote replaces it. CDEK rates continue to be provider-confirmed.

The frontend is progressive enhancement:

1. Searchable provider tabs and pickup lists work without external map scripts.
2. With a valid JavaScript API key, the same normalized points are rendered on a Yandex map.
3. Selection is restored after every WooCommerce `updated_checkout` event.
4. A quote request updates WooCommerce checkout totals only after the server has persisted the confirmed quote.
5. Errors are shown inline and the unavailable provider is not silently priced at zero.

### Order lifecycle

The selected provider snapshot and confirmed quote are copied from session to order and shipping-item metadata during checkout. Provider creation services remain idempotent by checking stored external IDs.

- Cash on delivery: create the provider shipment on `woocommerce_checkout_order_processed` after the order and shipping items exist.
- Online payment: create on the existing paid/processing transition.
- Repeated hooks are safe and never create duplicate provider orders.
- Failures add an order note and a redacted integration log entry; the WooCommerce order itself remains valid.

## Error handling and security

- Validate all REST input and use nonces; provider credentials stay server-side.
- Cache pickup points and map clusters; do not cache a customer-specific quote across cart or destination fingerprints.
- Apply timeouts and existing bounded token refresh behavior.
- Do not expose raw provider errors, tokens, secrets, or create payloads in the browser.
- Reject stale or mismatched session selections at checkout validation.
- Preserve list fallback when maps are unconfigured or fail to load.

## Testing

- PHP unit tests cover settings sanitization, key diagnostic normalization, session invalidation, provider request construction, quote normalization, shipping-rate gating, order metadata, and COD/prepaid idempotency.
- JavaScript tests cover list fallback, map enhancement, restored selection after `updated_checkout`, and stale-request suppression.
- Existing commerce unit and smoke suites must remain green.
- Live Ozon acceptance still requires merchant OAuth and real SKUs because Ozon Delivery has no sandbox.


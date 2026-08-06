# Theobroma Loyalty Design

## Scope

Implement the loyalty requirements from “Перенос сайта на WP Theobroma.docx” inside the existing `theobroma-commerce` plugin. Registered customers receive 5% back in bonuses, may pay up to 20% of merchandise value with bonuses, and see balance plus history in WooCommerce My Account. Guest checkout remains unchanged. AI functionality is outside scope.

## Business rules

- One bonus equals one Russian ruble.
- Bonuses accrue only for registered customers and only after successful payment.
- The accrual base is the paid merchandise amount after all discounts, excluding shipping and shipping taxes.
- Accrual is 5%, rounded down to two decimal places.
- Redemption is limited by the customer's available balance and 20% of the merchandise amount before the bonus discount.
- Checkout reserves bonuses before the payment redirect. A failed, cancelled or expired unpaid order releases the reservation.
- Successful payment converts the reservation into a spend without changing the reserved amount a second time.
- Full or partial refunds reverse only the proportionate accrual and restore the proportionate spent bonuses.
- If refunded-order bonuses were already spent on another order, reversal creates a visible negative balance; further redemption remains unavailable until later accruals repay it.
- Every operation has a unique idempotency key derived from order, operation type and refund where applicable.

## Architecture

The feature belongs to `theobroma-commerce`, not the theme.

- `LoyaltyAccountRepository`: transactional account balance and reservation updates.
- `LoyaltyLedgerRepository`: append-only customer-visible journal with unique idempotency keys.
- `LoyaltyCalculator`: pure 5% accrual and 20% redemption calculations using integer kopecks.
- `LoyaltyService`: reserve, spend, release, accrue and reverse workflows.
- `WooLoyaltyLifecycle`: WooCommerce payment, cancellation and refund hooks using WooCommerce CRUD/HPOS-compatible APIs.
- `LoyaltyCheckout`: authenticated checkout controls and a session-backed virtual coupon, with server-side recomputation on every request.
- `LoyaltyAccountEndpoint`: `/my-account/bonuses/` balance and paginated operation history.

Two custom InnoDB tables are used: one row per customer account and an append-only ledger. Monetary values are stored as integer kopecks. A database transaction locks the account row, applies the balance mutation and inserts the unique ledger record atomically. No balance is inferred from browser/session data.

## Checkout flow

1. An authenticated customer enters the desired bonus amount.
2. The server recomputes the 20% ceiling and available balance and stores only the accepted amount in the WooCommerce session.
3. A virtual fixed-cart coupon represents the accepted amount so WooCommerce totals, taxes, payment amount and order e-mails stay consistent.
4. When WooCommerce creates the order, the service atomically reserves the accepted amount and records it in order metadata.
5. Payment success converts the reservation to a spend and accrues 5% on the actually paid merchandise value.
6. Payment failure/cancellation releases the reservation. Refund hooks perform proportional reversal using idempotent refund keys.

## Customer interface

- Add “Бонусы” to the existing My Account navigation.
- Dashboard card shows balance and concise rules.
- Bonus page shows balance, available/reserved amounts and a dated ledger with order links.
- Checkout shows balance, the maximum allowed amount, an accessible numeric input and clear validation messages.
- The UI reuses current Theobroma typography, colors and controls and remains usable at desktop, tablet and 390px mobile widths.

## Security and failure handling

- All mutations require the authenticated customer and verified WooCommerce checkout/nonces.
- Browser-submitted amounts are never trusted.
- Database constraints prevent duplicate operations. Ordinary reservations cannot make available balance negative; a refund reversal may record a debt so accounting is not silently lost.
- Provider webhook retries are safe because order operations are idempotent.
- If storage or validation fails, checkout continues without a bonus discount rather than creating an underpaid order.
- Secrets and external integrations are unrelated to the loyalty tables.

## Verification

- Pure calculator boundary tests for rounding and 20% limits.
- Repository/service tests for double calls, insufficient balance, reservation release and refund reversals.
- WordPress smoke tests for tables, endpoint, hooks and HPOS-safe order metadata.
- Playwright checks for registration, account balance/history and bonus checkout at desktop and mobile widths.

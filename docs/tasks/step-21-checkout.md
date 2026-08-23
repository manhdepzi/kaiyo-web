# Step 21 — Checkout Task Record

- Status: `DONE — PROVIDER-NEUTRAL E2E CLOSED BY STEPS 23–24`
- Inputs: Steps 03, 07–08 and 12–20 `DONE`; D-001/D-002/D-004 approved
- Provider-neutral integration E2E is closed by the Step 23 Payment and Step 24 Shipping bindings; named external providers remain separately gated by approved contracts.

## Scope

Implement idempotent Checkout orchestration that validates customer/address/shipping intent, authoritatively reprices every Cart line, revalidates stock, creates one pending Order snapshot and reserves required Inventory in one approved transaction boundary. External payment and shipping behavior is accessed only through ports.

## Acceptance

- [x] Repeated/concurrent submit produces one authoritative Order and reservation outcome.
- [x] Cart advisory values are ignored; current Pricing and Inventory determine acceptance.
- [x] Any invalid line, price/stock conflict or port preparation failure leaves no partial Order/reservation.
- [x] Order/line/address snapshots are immutable inputs for later state handling.
- [x] Payment and Shipping contracts are provider-neutral; Steps 23–24 bind and exercise both registrations in the authoritative Checkout transaction.

## Evidence

- Migration `2026_08_23_000009` creates authoritative Orders, immutable line/address snapshots, initial status history and one-result Checkout operations; checked-out Carts become terminal and permit a new active Cart.
- `PlaceCheckoutOrder` locks Customer/Cart/lines/Variants/pricing/inventory in deterministic order, ignores advisory Cart fields, snapshots current Pricing, allocates active warehouse balances, reserves with D-002 semantics and commits one Pending Order in a single MySQL transaction.
- Tax, Shipping and Payment preparation are side-effect-free ports. Default adapters fail closed until effective tax configuration and Steps 23–24 provider bindings exist; no external call is made inside the commerce transaction.
- Retry by operation key or Cart returns the same Order/Reservation when the request hash matches and rejects conflicting reuse. A two-process MySQL probe used an explicit barrier and both workers returned Order `1`/Reservation `1`; final counts were one Order, one Reservation and exactly `7.0000` reserved.
- SQLite Checkout core: 4 tests/24 assertions plus one MySQL-only trigger test skipped. Full SQLite suite: 75 passed/496 assertions with one intentional skip. MySQL 8.4 combined critical suite: 25 tests/103 assertions; Checkout contributed 5 tests/26 assertions including trigger enforcement.
- MySQL contained 11 relevant Checkout CHECK constraints and four immutable snapshot triggers. Full migration rollback removed all application tables/triggers (only Laravel's empty migration ledger remained), then `kaiyo_test` was dropped. Live `kaiyo` remained untouched.
- PHPStan level 8, Pint and Vite production build pass. Step 24 closes the provider-neutral Checkout gate by asserting one Payment/Attempt and one complete Shipment for the accepted Order; named external-provider certification remains contract-specific.

# Step 17 — Inventory Task Record

- Status: `DONE`
- Inputs: D-002 approved; Steps 03, 05–07, 10, 13, 15–16 `DONE`

## Scope

Implement Warehouse, Variant stock balances, append-only movements, source-keyed reservations/items and locked idempotent reserve/release/commit/payment-expiry operations. B2C reserves on Order creation, B2B on quote-to-order, commit occurs on dispatch, V1 never backorders, and verified payment prevents automatic expiry.

## Acceptance

- [x] `available = on_hand - active_reserved` never becomes negative.
- [x] Reserve locks balances in deterministic order and creates one outcome per source operation.
- [x] Release, commit and eligible expiry are idempotent terminal transitions with append-only movements.
- [x] Commit cannot exceed active reservation; release/expiry cannot affect committed stock.
- [x] Payment-verified reservations do not auto-expire and reservation renewal is absent in V1.
- [x] Permission-scoped adjustment uses dual control and cannot bypass movement evidence.
- [x] Parallel MySQL reservation test proves no oversell; retry/rollback/constraint suites pass.

## Evidence

- Migration `2026_08_23_000006` defines warehouses, constrained balances, source-keyed reservations/items, immutable movements and dual-control adjustments.
- D-004 TTL values are environment-overridable configuration: online gateway 30 minutes and bank transfer 1,440 minutes; COD has no automatic expiry.
- SQLite/full suite: 57 tests and 416 assertions passed; Inventory suite contributes 6 tests and 27 assertions.
- MySQL 8.4 isolated `kaiyo_test` schema: combined Inventory/Search critical suite passed with 11 tests/41 assertions; 76 total CHECK constraints and both append-only movement triggers were present.
- Real two-process MySQL probe: exactly one of two concurrent reservations for 7 of 10 units succeeded; authoritative reserved quantity was 7 and one movement existed.
- Direct movement update was rejected by MySQL trigger with SQLSTATE `45000`; Step 17 rollback removed all six tables and both triggers before the isolated schema was dropped.
- PHPStan level 8, Pint and Vite production build passed. The live `kaiyo` schema was not migrated or mutated.

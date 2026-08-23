# Step 23 — Payment Task Record

- Status: `DONE — PROVIDER-NEUTRAL CORE; NAMED ONLINE PROVIDER DEFERRED`
- Inputs: Steps 03, 05–09, 13, 16 and 21–22 `DONE`; D-004 policy and D-007 provider-neutral baseline approved

## Scope

Implement separate Payment, Attempt, immutable Transaction, verified Provider Event, full Refund and Reconciliation truth. Bind Checkout registration, verified Order confirmation, Inventory payment protection and cancellation/refund preparation without enabling an unapproved online provider.

## Acceptance

- [x] Every Checkout Order receives exactly one Payment and initial Attempt with the immutable payable amount/currency.
- [x] COD and bank transfer are available under D-004; online gateway fails closed unless a named adapter/configuration is explicitly enabled.
- [x] Provider callbacks are authenticated by the selected adapter before persistence and deduplicated by provider event identity.
- [x] Paid/failed/unknown/out-of-order results cannot corrupt terminal Payment or Order truth; unknown results remain visible for reconciliation.
- [x] Verified full payment protects Inventory and confirms Pending Order through the existing evidence-bound Order action.
- [x] Paid pre-dispatch cancellation creates one full-refund requirement; proposal and approval use distinct authorized actors; completion is idempotent.
- [x] Partial refund, installment, deposit and client-declared success remain outside V1.

## Evidence

- Migration `2026_08_23_000011` creates six Payment-owned tables with FK/unique/state/amount constraints, generated active-case uniqueness and immutable MySQL evidence triggers.
- `PaymentLifecycleService` validates Checkout configuration, registers one Payment/Attempt, persists a verified charge once and emits only server-verified evidence.
- `ProcessPaymentWebhook` enforces body bounds, adapter signature verification, provider/payment/amount/currency context, durable event identity, terminal out-of-order protection and unknown-result reconciliation.
- `PaymentCancellationAdapter` blocks cancellation while outcome is unknown, returns no money action when unpaid, and creates one full refund requirement when verified paid.
- `ManageFullRefund` enforces `payments.refund_propose` / `payments.refund_approve`, separation of duties, exact paid balance and one immutable completion transaction.
- SQLite Payment suite: 3 tests/29 assertions plus one intentional MySQL-only trigger test; full suite: 81 passed/541 assertions with two MySQL-only skips.
- MySQL 8.4 combined critical suite: 32 tests/150 assertions; focused Payment rerun passed 4/31. Payment schema exposed nine CHECK constraints and eight evidence triggers; mutation/delete tests and standalone migration rollback/re-migrate passed.
- PHPStan level 8 and Pint pass. The isolated `kaiyo_test` schema was dropped; live `kaiyo` remained unchanged at zero tables.

## Deferred provider gate

No real online endpoint, provider SDK, secret, account or payload contract is enabled. Enabling one requires the named D-007 provider addendum, official signed fixtures, secret rotation/replay policy and staging reconciliation evidence.

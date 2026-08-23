# Step 24 — Shipping Task Record

- Status: `DONE — PROVIDER-NEUTRAL CORE`
- Inputs: Steps 03, 05–09, 13, 17 and 21–23 `DONE`; D-004/D-007 provider-neutral baseline approved
- Provider boundary: configured/manual V1 methods are enabled only by effective configuration; named carriers remain disabled until an approved adapter/contract is registered.

## Scope

Implement one Shipment per V1 Order, complete Shipment-item lineage, configured/manual fee preparation, evidence-bound warehouse transitions, carrier booking isolation, authenticated monotonic tracking, reconciliation and append-only pre-delivery correction. Shipping advances Order only through the approved Order action and commits Inventory only at dispatch.

## Acceptance

- [x] Checkout fails closed without an effective Shipping method and snapshots the configured VND fee/revision when one is active.
- [x] Every accepted Checkout creates exactly one Shipment containing every Order line and also retains the Payment registration from Step 23.
- [x] Ready, pack and dispatch require permission, expected version, legal Order/Shipment state and stable idempotency evidence.
- [x] Dispatch advances Order to Shipping and commits reserved Inventory exactly once.
- [x] Missing carrier adapters fail closed; carrier timeout/unknown result opens reconciliation without blocking the commerce core or duplicating a booking.
- [x] Carrier events are adapter-authenticated, bounded, durably deduplicated, conflict-checked and monotonic; late/out-of-order events cannot regress terminal truth.
- [x] Tracking correction is permission-gated, append-only and forbidden after Delivered.

## Evidence

- Migration `2026_08_23_000012` creates seven Shipping-owned tables with Order/line lineage, VND/configuration constraints, stable operation/event identities and immutable MySQL evidence triggers.
- `ShippingConfigurationService` is both Checkout preparation and registration port: it validates an effective configured method, snapshots its fee/revision and registers one complete draft Shipment inside the Checkout transaction.
- `ManageShipmentLifecycle` invokes `AdvanceOrder` for pack/dispatch; no Shipping code writes Order or Inventory state directly.
- `CarrierRegistry` and `CarrierAdapter` isolate provider behavior. Booking calls occur outside the database transaction; timeout becomes visible `booking_unknown` plus one open reconciliation case, while an unregistered carrier is rejected before the call.
- `ProcessCarrierEvent` verifies the adapter signature before persistence, rejects identity replay with changed payload, applies only monotonic state and uses the Order action for delivery. `CorrectTracking` retains original event evidence.
- SQLite Shipping suite passes 4 tests/30 assertions plus one intentional MySQL-only trigger test; full suite passes 85 tests/571 assertions with three intentional MySQL-only skips.
- MySQL 8.4 combined critical suite passes 37 tests/182 assertions. Shipping exposes seven CHECK constraints and seven evidence triggers; mutation/delete rejection and standalone rollback/re-migrate pass.
- PHPStan level 8 and Pint pass. The isolated `kaiyo_test` schema was removed after verification; live `kaiyo` remained unchanged at zero tables.

## Residual boundary

- No named carrier/provider is enabled, no provider credential is stored and no multi-shipment/split-shipment workflow is introduced in V1.
- Provider-specific webhook fields, retry budgets and operational credentials require an approved D-007 contract/addendum before adapter registration.

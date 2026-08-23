# Step 26 — Quote to Order Task Record

- Status: `DONE — TRANSACTIONAL CORE`
- Inputs: Steps 17, 21–25 `DONE`; D-002 quote-to-order reservation timing and D-003 conversion authority approved

## Scope

Convert exactly one current Accepted quotation revision into exactly one Order, preserving commercial/address/line snapshots, reserving Inventory at conversion, initializing provider-neutral Payment and Shipping, and making retries/concurrent contenders converge on the authoritative result.

## Acceptance

- [x] `quotes.convert` is checked server-side at the target Customer scope.
- [x] Only the current Accepted revision can convert; an owned quote cannot change Customer.
- [x] Order totals, addresses, Tax/Shipping evidence and pricing snapshot IDs are copied from the immutable revision without repricing.
- [x] Payment preparation, Payment/Attempt and complete Shipment lineage initialize in the same outer transaction.
- [x] Inventory allocation and `quote_to_order` reservation occur atomically; insufficient stock rolls back every Order/Payment/Shipment/conversion effect.
- [x] Revision row locking, one conversion row per revision, one Order per revision and stable request hashes make exact retries and concurrent contenders converge on one result.
- [x] Converted state and operation/audit lineage are durable and cannot be replayed with different Customer/actor/input.

## Evidence

- Migration `2026_08_23_000014` makes Order source an XOR of Cart/Quote revision, adds a unique Quote revision FK, conversion outcome table, source CHECK and an updated immutable Order trigger.
- `ConvertQuotationToOrder` locks revision/Quote/Customer and stock in deterministic order, reuses the approved Inventory reservation service and registers Payment/Shipping before commit.
- `InventoryAllocator` is shared by Checkout and Quote conversion, preventing allocation-rule drift.
- Full SQLite suite passes 102 tests/638 assertions with four intentional MySQL-only skips. MySQL 8.4 critical suite passes 46/242; focused Quotation/Conversion passes 9/60.
- Standalone Step 26 rollback restores non-null Cart-only Order schema while retaining all Step 25 tables; re-migration restores the conversion table, Quote FK, XOR CHECK and snapshot trigger.
- PHPStan level 8 and Pint pass. Isolated `kaiyo_test` was removed; live `kaiyo` remained unchanged at zero tables.

## Residual boundary

- This step does not expose UI/controllers; Steps 29–31 own customer/Sales delivery surfaces.
- Named providers remain disabled. Provider certification remains gated by an approved contract/addendum.

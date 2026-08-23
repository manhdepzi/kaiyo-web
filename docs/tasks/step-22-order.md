# Step 22 — Order Task Record

- Status: `DONE`
- Inputs: Steps 03, 05–08, 13, 16–17 and 20–21 `DONE`

## Scope

Implement ORD-L1 as an expected-version, evidence-bound and idempotent state machine. Implement CANCEL-D1 request/decision with server authorization, atomic Inventory release and a provider-neutral Payment compensation preparation hook.

## Acceptance

- [x] Only approved forward transitions are accepted; payment state never becomes an Order state.
- [x] Duplicate/stale/out-of-order evidence cannot duplicate or reverse a transition.
- [x] Cancellation is request-then-decision, only from Pending/Confirmed, and never self-decided.
- [x] Approved cancellation atomically releases active Inventory and persists Payment compensation preparation.
- [x] History and decision evidence are immutable and permission scoped.

## Evidence

- Migration `2026_08_23_000010` expands Pending into the exact ORD-L1 forward lifecycle, adds expected-version transition operations, cancellation request/decision evidence and lifecycle timestamps without introducing Payment states into Order.
- `AdvanceOrder` rejects skipped/backward/stale transitions, requires server evidence for confirmation, applies each evidence identity once and commits Inventory exactly once on `Packed → Shipping` dispatch.
- `ManageOrderCancellation` enforces `orders.cancel_request`/`orders.cancel_decide` against the server-resolved Customer scope, separates requester and decider, denies Processing-or-later and atomically prepares Payment compensation plus releases Inventory before marking Cancelled.
- Payment cancellation is provider-neutral and default fail-closed until Step 23. Tests bind a side-effect-free preparation adapter; provider execution/reconciliation remains Payment-owned.
- Order commercial fields, line/address snapshots, history, transition operations and terminal cancellation evidence are protected by MySQL triggers. Seven relevant CHECK constraints and seven Step 22 lifecycle/snapshot triggers were present.
- Order lifecycle suite passes 3 tests/16 assertions. Full SQLite suite passes 78 tests/512 assertions with one intentional MySQL trigger skip; MySQL 8.4 combined critical suite passes 28 tests/119 assertions.
- Migration `000010` standalone rollback removed its tables/triggers and restored the Step 21 state constraint; re-migration passed. `kaiyo_test` was dropped afterward and live `kaiyo` remained at zero tables.
- PHPStan level 8 and Pint pass. Step 23 must bind authoritative Payment confirmation and cancellation compensation; Step 24 owns Shipment evidence while calling this transition contract.

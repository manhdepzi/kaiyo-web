# Step 22 — Order Task Record

- Status: `DONE`
- Inputs: Steps 03, 05–08, 13, 16–17 and 20–21 `DONE`

## Scope

Implement ORD-L1 as an expected-version, evidence-bound and idempotent state machine. Implement CANCEL-D1 request/decision with server authorization, atomic Inventory release and a provider-neutral Payment compensation preparation hook.

## Acceptance

- [x] Only approved forward transitions are accepted; payment state never becomes an Order state.
- [x] Duplicate/stale/out-of-order evidence cannot duplicate or reverse a transition.
- [x] Cancellation is request-then-decision, only from Pending/Confirmed, and never self-decided.
- [x] Verified Customer owners can submit their own request through the Portal; staff decisions require explicit `orders.cancel_decide`, confirmed 2FA and a separate actor.
- [x] Approved cancellation atomically releases active Inventory and persists Payment compensation preparation.
- [x] History and decision evidence are immutable and permission scoped.
- [x] Every accepted forward/cancellation state change persists a minimal versioned fact atomically and an exact retry cannot duplicate it.

## Evidence

- Migration `2026_08_23_000010` expands Pending into the exact ORD-L1 forward lifecycle, adds expected-version transition operations, cancellation request/decision evidence and lifecycle timestamps without introducing Payment states into Order.
- `AdvanceOrder` rejects skipped/backward/stale transitions, requires server evidence for confirmation, applies each evidence identity once and commits Inventory exactly once on `Packed → Shipping` dispatch.
- `ManageOrderCancellation` enforces `orders.cancel_request`/`orders.cancel_decide` against the server-resolved Customer scope, separates requester and decider, denies Processing-or-later and atomically prepares Payment compensation plus releases Inventory before marking Cancelled.
- `OrderStateFactRecorder` persists `commerce.order.state.changed` v1 using Order public ID/version identity. Its payload contains only previous/resulting states and version; customer, actor, payment, shipping and evidence details remain in their authoritative protected domains.
- Forward transition retries and terminal cancellation retries emit one fact per resulting Order version. A forced outer rollback test proves the Order state, transition operation and fact roll back together.
- Payment cancellation is provider-neutral and default fail-closed until Step 23. Tests bind a side-effect-free preparation adapter; provider execution/reconciliation remains Payment-owned.
- Order commercial fields, line/address snapshots, history, transition operations and terminal cancellation evidence are protected by MySQL triggers. Seven relevant CHECK constraints and seven Step 22 lifecycle/snapshot triggers were present.
- Current Order lifecycle suite passes 4 tests/30 assertions, including idempotent in-app Notification consumption. The combined Order/Payment/Shipping/Checkout/Quotation regression previously passed 24 tests/181 assertions with four intentional MySQL-only skips before the consumer slice.
- Migration `000010` standalone rollback removed its tables/triggers and restored the Step 21 state constraint; re-migration passed. `kaiyo_test` was dropped afterward and live `kaiyo` remained at zero tables.
- PHPStan level 8 and Pint pass. Step 23 must bind authoritative Payment confirmation and cancellation compensation; Step 24 owns Shipment evidence while calling this transition contract.

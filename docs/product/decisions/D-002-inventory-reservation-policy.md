# D-002 — V1 Inventory Reservation Lifecycle

## 1. Decision control

- Status: `APPROVED`
- Decision ID: `D-002`
- Approved by: Product Owner (user)
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval evidence: the user selected option `1` for reservation timing and then option `1` for payment-method-based expiry
- Applies to: V1 inventory, checkout, order and quote-to-order business rules
- Does not approve: concrete per-payment-method TTL values or payment methods/terms (`D-004`), database schema, public API or event contracts

## 2. Approved lifecycle

### B2C checkout

- Reserve stock atomically when the authoritative order is created during checkout, before invoking or awaiting an external payment result.
- Order creation, idempotency outcome and the required inventory reservation must share an approved transaction boundary so an order is not created with a missing required reservation.
- Insufficient stock rejects the defined checkout/order creation operation without a partial order or reservation.

### B2B quotation

- Accepting a quotation does not reserve stock by itself.
- Reserve stock atomically when the accepted quotation revision is converted into its authoritative order.
- If stock is insufficient at conversion, the conversion fails without partially accepting/converting the quotation or creating an order.

### Release and commit

- Release an active reservation when its owning order is canceled through an approved transition.
- Release an active reservation when it reaches an approved applicable expiry condition.
- Commit reserved stock consumption when warehouse/fulfillment confirms dispatch of the corresponding quantity.
- Release and commit operations are retry-safe and idempotent; a terminal reservation cannot be applied twice or implicitly reopened.

## 3. Backorder policy

V1 does not support backorders. A reservation must not make authoritative available stock negative. Insufficient availability fails closed.

## 4. Integrity invariants

- MySQL is authoritative for on-hand, active reservations and committed/released inventory effects.
- Redis, cache and search cannot authorize a reservation or serve as inventory truth.
- Concurrent reservations for the same stock scope must be serialized or use an equivalent approved atomic consistency mechanism.
- Each order line can have at most one effective reservation outcome for the same reservation identity.
- Partial failure must not leave order, quotation conversion and inventory truth inconsistent.
- External payment/carrier/AI availability is not part of the inventory consistency boundary.

## 5. Approved expiry policy

- Automatic expiry applies only while the owning order is waiting for payment or an approved payment confirmation condition.
- Expiry duration is configured per approved payment method. The concrete methods, confirmation conditions and TTL values belong to D-004 and must not be hard-coded.
- After payment or an approved payment-term condition is verified server-side, the reservation does not auto-expire under this policy.
- V1 does not allow manual renewal or extension of an expired or expiring reservation.
- An expired reservation is released idempotently. A later attempt to proceed must obtain a new authoritative reservation through an approved order workflow; the old reservation is never reopened.
- Payment callback and expiry races must use authoritative state/concurrency controls so only one valid outcome wins and stock is never released after a verified protected condition.

## 6. Required verification

- Parallel reservation tests prove available stock never becomes negative and only eligible requests succeed.
- Checkout double-submit tests return one authoritative order/reservation result.
- Concurrent quote conversion creates exactly one order and one effective reservation outcome.
- Insufficient-stock failures roll back the entire defined order/conversion transaction.
- Duplicate release and duplicate dispatch/commit commands produce one inventory effect.
- Cancellation versus dispatch races produce one valid terminal outcome under the approved transition contract.
- Redis/search outage or stale data cannot authorize overselling.
- Expiry tests cover each D-004 payment configuration, duplicate scheduler execution, payment-versus-expiry races and prohibition of manual renewal.

## 7. Consequences and follow-up gates

- Reservation trigger, B2B timing, expiry behavior, dispatch commit and V1 no-backorder behavior may be used to reconcile proposed rules.
- Concrete payment methods, confirmation conditions and TTL values remain blocked by D-004; D-002 must not be interpreted as selecting them.
- Step 03 remains blocked by D-004 through D-005 and final Product/Architecture approval of the complete Business Rules Matrix.
- Schema, indexes, API and events remain blocked until their later design gates are approved.

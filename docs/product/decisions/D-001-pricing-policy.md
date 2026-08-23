# D-001 — V1 Pricing Precedence, Stacking and Rounding

## 1. Decision control

- Status: `APPROVED`
- Decision ID: `D-001`
- Approved by: Product Owner (user)
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval evidence: the user selected option `1`, the proposed specificity-based V1 pricing policy
- Applies to: V1 pricing rules, PricingEngine, quotation pricing snapshots, checkout repricing and order pricing snapshots
- Does not approve: discount authority/limits (`D-003`), tax/shipping/payment calculations (`D-004`), database schema, public API or event contracts

## 2. Approved decision

V1 resolves the effective unit price by increasing specificity in this order:

1. Retail/base price.
2. Eligible promotion price.
3. Eligible B2B tier or contract price.
4. Eligible customer or company override price.
5. Valid quotation-negotiated price.

An eligible winner at a later layer replaces the price produced by the previous layer. V1 does not stack discounts or combine multiple price adjustments across these layers.

Within each layer, at most one rule may win according to an explicit configured priority. If two eligible rules at the same layer have the same winning priority, pricing fails closed as an ambiguous configuration; the engine must not choose arbitrarily.

A valid quotation-negotiated price is the final authoritative unit price for that quotation revision and any approved conversion snapshot. Authority to create or approve that negotiated price remains governed by `D-003`.

## 3. Calculation and rounding

- Monetary calculations must use decimal or minor-unit-safe values; binary floating-point is forbidden.
- The effective unit price is resolved first using Section 2.
- Each line amount is rounded using `HALF_UP` to the precision of its currency.
- The document merchandise total is the sum of the immutable rounded line snapshots.
- Tax, shipping and any approved document-level adjustment remain governed by `D-004` and `D-003`; this decision does not define them.
- Amounts must be non-negative and all inputs within one pricing calculation must use the same currency unless a future approved rule explicitly defines conversion.

## 4. Snapshot and authority invariants

- Issued quotation revisions and placed orders retain immutable pricing snapshots, including the effective unit price, source layer, source/configuration revision, material inputs and rounding policy.
- Later pricing configuration changes must not rewrite historical quotation or order snapshots.
- UI, controllers and AI may request or display a calculation but cannot calculate or override the authoritative price.
- An exception to non-stacking or precedence requires a new approved business-rule revision; it cannot be introduced as configuration or implementation detail alone.

## 5. Failure behavior

Pricing fails without an authoritative price or partial commerce write when:

- no valid retail/base price exists;
- currency or quantity inputs are invalid;
- the winning rule is ambiguous;
- a selected override or quotation price lacks current authority/approval under `D-003`; or
- arithmetic or configuration validation fails.

## 6. Required verification

- Golden tests cover every precedence layer and confirm later eligible layers replace earlier layers.
- Tests prove V1 does not stack discounts across or within layers.
- Equal-priority winner conflicts fail closed and never select by database/order iteration.
- Boundary tests cover `HALF_UP` at the configured currency precision and prove document merchandise totals equal the sum of rounded line snapshots.
- Historical snapshot tests prove configuration changes do not alter issued quotations or placed orders.
- Authorization tests prove unauthorized actors cannot establish manual/customer/company/quotation overrides.
- Architecture tests prove UI/controllers/AI do not contain an alternative authoritative pricing algorithm.

## 7. Consequences and follow-up gates

- The pricing portion of Step 03 can now be reconciled against this decision.
- Step 03 remains blocked by `D-004` through `D-005` and final Product/Architecture approval of the complete Business Rules Matrix.
- Schema, index, API and event work remains blocked until their respective later design gates are approved.

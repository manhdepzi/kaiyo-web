# Step 03 — Business Rules Revalidation Report

## 1. Control

- Status: `DONE — V1 POLICY APPROVED; CONTRACT HANDOFF OPEN`
- Promptbook step: `03 — Business Rules Matrix`
- Reviewed artifact: [Business Rules Matrix](./business-rules-matrix.md)
- Review date: 2026-08-23 (Asia/Bangkok)
- Scope: all 47 unique `BR-*` rules plus eight cross-domain invariants
- Change boundary: classification and gap reporting only; this report does not approve state names, actors, permissions, thresholds, schema, API or events

Sources applied in precedence order:

1. [Product Requirements v0.1](../product/product-requirements.md) and approved [D-001](../product/decisions/D-001-pricing-policy.md), [D-002](../product/decisions/D-002-inventory-reservation-policy.md), [D-003](../product/decisions/D-003-discount-quotation-approval-policy.md).
2. No accepted ADRs exist.
3. No approved database/API/event contracts exist.
4. Architecture/Functional Specification v1.1.
5. No production application code exists.

Remaining consolidated inputs are in the [D-004–D-007 Approval Pack](../product/decision-approval-pack-D004-D007.md).

The Product Owner selected option `1` on 2026-08-23, approving the recommended bundle in the [Step 03 Approval Questionnaire](./step-03-approval-questionnaire.md). This closes the policy selection for GAP-03-01/02/06–13, while D-004/D-005 and conditional D-006/D-007 inputs remain open.

## 2. Inventory verification

| Domain | Rule range | Count | Revalidation result |
| --- | --- | ---: | --- |
| CRM | BR-CRM-001–004 | 4 | `POLICY SELECTED — D-005/D-006 BLOCKED` |
| Pricing | BR-PRC-001–004 | 4 | `PARTIALLY ALIGNED` |
| Inventory | BR-INV-001–005 | 5 | `POLICY ALIGNED — D-004/D-005 BLOCKED` |
| Quotation | BR-QTE-001–005 | 5 | `QTE-L1/QVALID-D1 SELECTED — D-004/D-005 BLOCKED` |
| Order | BR-ORD-001–005 | 5 | `ORD-L1/CANCEL-D1 SELECTED — D-004/D-005 BLOCKED` |
| Payment | BR-PAY-001–005 | 5 | `BLOCKED D-004` |
| Shipping | BR-SHP-001–004 | 4 | `BLOCKED D-004/D-007` |
| Tax | BR-TAX-001–004 | 4 | `BLOCKED D-004` |
| Authorization | BR-AUTH-001–005 | 5 | `BLOCKED D-005/D-006` |
| AI writes | BR-AIW-001–006 | 6 | `V2 DEFERRED` |
| **Total** |  | **47 unique rules** | Complete inventory; recommended policy bundle partially approved; dependent rules remain gated |

All required matrix columns are present. File existence and complete columns do not constitute business approval.

## 3. Approved-decision alignment

### D-001 pricing

- BR-PRC-001 reflects specificity replacement, V1 non-stacking, deterministic priority and ambiguity failure.
- BR-PRC-003 reflects `HALF_UP` at currency precision and document merchandise total as the sum of rounded line snapshots.
- BR-PRC-002 is also constrained by D-003 and remains dependent on D-004 total/currency basis and D-005 actor scope.
- BR-PRC-004 still needs D-005 to identify configuration proposers/approvers and Step 07 to define safe effective-interval constraints.

Result: `ALIGNED AS PROPOSAL`; not implementation-approved.

### D-002 inventory

- BR-INV-001–003 reflect B2C reserve at authoritative order creation, B2B reserve at quote-to-order, no backorder, payment-method expiry, no manual renewal and commit on dispatch.
- D-004 must still provide concrete payment methods/TTL values and valid confirmation conditions.
- BR-INV-004 manual adjustment authority remains blocked by D-005.
- BR-INV-005 matches MySQL truth/derived-cache governance.

Result: `ALIGNED AS PROPOSAL`; expiry execution and actor permissions remain blocked.

### D-003 discount/quotation approval

- BR-PRC-002 and BR-QTE-002 apply the highest trigger across discount, quotation value and below-cost risk.
- Boundary meanings are explicit: Sales `≤5%`; Manager `>5%–15%` or final total `≥100M VND`; Finance `>15%–25%`, total `≥500M VND`, or below approved cost; `>25%` rejects.
- BR-QTE-003 invalidates approval after material financial/terms changes and keeps prior approved/issued revisions immutable.
- D-004 must confirm VND and total composition; D-005 must confirm resource/actor scope.

Result: `ALIGNED AS PROPOSAL`; not implementation-approved until dependent decisions and full matrix approval.

## 4. Source conflicts resolved by Product Owner selection

### GAP-03-01 — Quotation lifecycle vocabulary

Higher-priority approved PRD says to use the specification lifecycle, whose baseline is:

```text
Draft → Submitted → Processing → Sent → Viewed
                              ├→ Accepted → Converted
                              ├→ Rejected
                              └→ Expired
```

The current matrix instead uses mainly `Draft → Issued → Accepted/Expired` and combines acceptance with conversion in BR-QTE-004.

Decision: `QTE-L1` approved. Acceptance and conversion are separate; `Sent` is the stored transition independent of retryable notification delivery; `Viewed` is informational only. Actor/resource scopes remain D-005 and persistence/API/event representation remains Steps 05–08/48.

### GAP-03-02 — Order lifecycle vocabulary

Specification baseline:

```text
Pending → Confirmed → Processing → Packed → Shipping → Delivered → Completed
       └──────────────── controlled Cancelled / Refunded paths
```

The matrix uses `Draft`, `PendingPayment`, `Placed`, `Paid`, `Fulfilling`, `Shipped`, `Completed`, mixing payment evidence into order state.

Decision: `ORD-L1` approved. Payment remains a separate aggregate/state machine. `CANCEL-D1` permits request/authorized cancellation only from Pending/Confirmed and denies Processing-or-later absent a separately approved exception. D-004 still defines qualifying confirmation/refund evidence; D-005 defines actors.

### GAP-03-03 — Payment lifecycle and B2B terms

Specification proposes `Pending → Processing → Paid/Failed`, then refund transitions, but explicitly leaves deposit, partial payment and terms to business confirmation. The current matrix is generic enough to model them but cannot select V1 behavior.

Resolution source: D004-C package and owner inputs in the approval pack.

### GAP-03-04 — VAT, invoice, shipping and total composition

Tax and shipping rows have safe invariants (snapshot, deterministic calculation, no client truth), but rates, classification, invoice eligibility, method/fee/free-shipping rules and total basis remain unapproved.

Resolution source: D004-A/B/D package and owner inputs.

### GAP-03-05 — Authorization and ownership

Authorization rows correctly require server-side deny-by-default behavior, but exact effective permissions, Company member capability, Sales/team ownership, reassignment, delegation, revocation freshness and break-glass approver are not approved.

Resolution source: D005-A package and owner inputs.

## 5. Additional business gaps selected, with dependent gates retained

These gaps were present in the PRD/specification or matrix. Their policy options are selected below, but each named dependency remains a STOP gate and the selection is not permission to invent fields/contracts.

| Gap | Selected option | Remaining dependency |
| --- | --- | --- |
| GAP-03-06 CRM duplicate/conversion | `CRM-D1` | D-005 data-steward/resource scope and Step 07 uniqueness/normalization contract |
| GAP-03-07 Privacy erasure/anonymization | `PRIV-D1` | D-006 retention/residency/legal hold and D-005 privacy operator |
| GAP-03-08 Promotion eligibility | `PROMO-D1` | D-004 currency/total implications, D-005 administration and Step 07 atomic redemption contract |
| GAP-03-09 Manual stock adjustment | `STOCK-D1` | D-005 eligible proposer/approver and Step 07 concurrency contract |
| GAP-03-10 Quote validity | `QVALID-D1` | D-005 Sales/Manager scope; 30 calendar days is approved from Sent |
| GAP-03-11 Cancellation/amendment/refund | `CANCEL-D1` | D-004 void/refund behavior and D-005 decision actors |
| GAP-03-12 Shipment correction | `SHIP-D1` | D-004 shipping package, D-005 Operations scope and D-007 provider contract |
| GAP-03-13 Tax correction | `TAX-D1` | D-004 document/provider/timing and D-005 Finance scope |

## 6. V1/V2 separation

BR-AIW-001–006 are retained as V2 proposals only. They must not be approved as V1 dependencies or implemented before V1 production gate and D-008.

V1 approval of the matrix must explicitly mark these six rules `V2 DEFERRED`, while approving the cross-domain invariant that commerce operates with AI disabled/unreachable.

## 7. Cross-domain invariant review

| Invariant | Result | Remaining evidence |
| --- | --- | --- |
| INV-001 MySQL truth | `ALIGNED` | Architecture/DB contracts and outage tests |
| INV-002 deterministic money | `ALIGNED D-001` | D-004 currency/tax basis and value-object tests |
| INV-003 transition concurrency | `ALIGNED` | Approved lifecycles and schema/locking plan |
| INV-004 post-commit side effects | `ALIGNED` | Event/outbox/queue contract |
| INV-005 immutable snapshots | `ALIGNED D-001–D-003` | Schema constraints and regression tests |
| INV-006 AI-independent commerce | `ALIGNED` | Module dependency test and AI-outage E2E |
| INV-007 deny-by-default authorization | `ALIGNED` | D-005 permission matrix and negative tests |
| INV-008 critical idempotency | `ALIGNED` | DB/API/event identity contracts and concurrency tests |

## 8. Step 03 exit checklist

- [x] Exactly 47 unique rules inventoried.
- [x] Every requested domain and required matrix field is present.
- [x] D-001–D-003 alignment reviewed.
- [x] Source lifecycle conflicts explicitly reported.
- [x] V1/V2 AI boundary explicitly reported.
- [x] Decision-ready options and trade-offs prepared for GAP-03-01 through GAP-03-13.
- [x] D-004 policy approved; missing concrete configuration/provider keeps affected capability disabled.
- [x] D-005 scoped authorization policy approved; exact permission identifiers handed to Step 13.
- [x] GAP-03-01/02/06–13 policy options selected; GAP-03-03–05 remain explicitly dependent on D-004/D-005.
- [x] Affected V1 rule rows revised to the selected vocabulary with unresolved dependencies preserved.
- [x] Product Owner delegated decision authority and Architecture review recorded for the complete V1 matrix revision.
- [ ] Required DB/API/Event contract work handed to Steps 07–08/48; no contract falsely marked complete.

Step 03 is `DONE` at business-policy level. Step 07–08/13/48 must produce the approved persistence/API/permission/event contracts before implementation of the affected layer. Missing legal retention or provider configuration remains a fail-closed/disabled capability and a production gate.

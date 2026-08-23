# Step 03 — Business Rules Approval Questionnaire

## 1. Control

- Status: `RECOMMENDED BUNDLE APPROVED — DEPENDENT GATES OPEN`
- Parent: [Step 03 Revalidation Report](./step-03-revalidation-report.md)
- Reviewed matrix: [Business Rules Matrix](./business-rules-matrix.md)
- Purpose: present decision-ready choices for GAP-03-01 through GAP-03-13 in one Product Owner review
- Boundary: the selected option IDs are approved policy inputs; their named D-004/D-005/D-006/D-007, contract and Architecture dependencies remain STOP gates

This questionnaire does not replace the [D-004–D-007 Approval Pack](../product/decision-approval-pack-D004-D007.md). The owner selected the full recommended bundle on 2026-08-23; any conditional behavior remains blocked until its named decision is approved.

## 2. Recommended approval bundle

The recommended coherent bundle is:

```text
QTE-L1, ORD-L1, CRM-D1, PRIV-D1, PROMO-D1, STOCK-D1,
QVALID-D1, CANCEL-D1, SHIP-D1, TAX-D1
```

Approval of the bundle still requires D-004/D-005 and D-006 retention inputs. It does not approve schema, public contracts or provider integrations.

## 3. Lifecycle decisions

### GAP-03-01 — Quotation lifecycle

#### `QTE-L1` — Specification lifecycle (recommended)

```text
Draft → Submitted → Processing → Sent → Viewed
                              ├→ Accepted → Converted
                              ├→ Rejected
                              └→ Expired
```

- `Submitted`: customer/requester has submitted a complete request.
- `Processing`: authorized Sales has taken responsibility for preparation/review.
- `Sent`: an immutable approved quotation revision has been issued for customer access; notification delivery is a retry-safe side effect and does not redefine the stored transition.
- `Viewed`: informational customer-access evidence; it must not authorize another transition.
- `Accepted`: exact unexpired issued revision accepted by an eligible actor.
- `Converted`: the accepted revision has produced exactly one order through the approved transaction/idempotency rule.
- Rejected/Expired are terminal for that revision; revision history remains immutable.

Trade-off: matches the higher-priority PRD/specification and provides operational visibility, but requires more transition tests than the shortened matrix.

#### `QTE-L2` — Simplified lifecycle

Use `Draft → Issued → Accepted/Rejected/Expired → Converted` and remove Submitted/Processing/Sent/Viewed distinctions.

Trade-off: fewer states, but loses Sales/customer timeline detail and requires an explicit PRD/specification revision.

### GAP-03-02 — Order lifecycle

#### `ORD-L1` — Separate order/payment/fulfillment state machines (recommended)

Use the specification order lifecycle:

```text
Pending → Confirmed → Processing → Packed → Shipping → Delivered → Completed
       └──────────────── controlled Cancelled path
```

- Payment state remains exclusively in the Payment aggregate; `Paid` is not an order state.
- D-004 defines which verified payment/term evidence permits `Pending → Confirmed`.
- Inventory reservation/commit follows D-002 independently of UI labels.
- Refund is a Payment process/evidence linked to the order; it does not silently overwrite historical order/financial snapshots.

Trade-off: prevents state coupling and supports reconciliation, at the cost of coordinated policies/read models.

#### `ORD-L2` — Combined commerce state

Keep matrix terms such as PendingPayment/Paid/Fulfilling inside Order.

Trade-off: initially simpler screens, but couples provider outcomes to order truth and conflicts with the specification's separate Payment model; not recommended.

## 4. CRM and privacy

### GAP-03-06 — Duplicate detection and conversion

#### `CRM-D1` — Exact normalized identity blocks, fuzzy review only (recommended)

- Company exact normalized tax code is a blocking duplicate signal when tax code exists and is verified for use.
- Person/contact exact normalized verified email or E.164-style normalized phone is a blocking duplicate signal within the approved business scope.
- Name/company-name similarity or partial contact matches only create a review candidate; they never auto-merge or auto-reject.
- Lead conversion is transaction-safe/idempotent. A confirmed existing Customer/Company is linked/reused rather than duplicated.
- Merge never deletes or rewrites commerce history. An eligible D-005 data-steward permission reviews the preview and confirms the target/source.

Trade-off: high deterministic integrity with manual handling for uncertain matches; requires normalization and review UI.

#### `CRM-D2` — Manual duplicate review only

Never block creation automatically; flag all suspected matches for later review.

Trade-off: fewer false-positive blocks, but duplicate accounts and concurrent conversions are more likely.

### GAP-03-07 — Privacy erasure/anonymization

#### `PRIV-D1` — Retention-aware anonymization (recommended)

- Verify requester identity/authority and enumerate affected data before mutation.
- Delete data only when D-006 retention/legal policy permits it.
- Retained order/payment/invoice/quotation/audit evidence is minimized or pseudonymized rather than destructively removed.
- Derived search/cache/provider copies are purged through retry-safe tracked jobs.
- The operation is resumable/idempotent and records proof without exposing the removed PII.

Trade-off: preserves integrity/legal evidence but requires the D-006 per-data-class retention matrix. No retention duration is approved here.

#### `PRIV-D2` — Hard-delete eligible customer graph

Delete all reachable customer data on an approved request.

Trade-off: simpler conceptually but can corrupt accounting/audit references and is prohibited until legal/retention review proves it safe.

## 5. Pricing and inventory operations

### GAP-03-08 — V1 promotion capability

#### `PROMO-D1` — One deterministic simple promotion winner (recommended)

- V1 promotion forms: percentage reduction or fixed-amount reduction; no formula/script execution.
- Eligibility may be configured by effective interval, minimum merchandise subtotal/quantity, product/category/brand and customer/company scope.
- Usage limit and per-customer limit apply only when configured and are enforced atomically.
- D-001 remains authoritative: one eligible promotion winner by explicit priority; no stacking; equal winning priority fails closed.
- Maximum reduction cannot exceed the D-003 authority/hard limit where manual/negotiated approval applies; final amount cannot be negative.
- Advanced campaign composition, loyalty and affiliate logic remain future scope.

Trade-off: covers common promotions without building an advanced engine; still needs atomic redemption and boundary tests.

#### `PROMO-D2` — No active promotions in V1

Retain the adapter/config boundary but operate only retail/B2B/customer/quotation layers.

Trade-off: fastest implementation but removes a V1 Admin/commerce capability suggested by the specification.

### GAP-03-09 — Manual inventory adjustment

#### `STOCK-D1` — Dual-control manual adjustment (recommended)

- Authorized Warehouse/Operations proposes a non-zero adjustment with reason and evidence.
- A distinct eligible Inventory Manager/Operations approver must approve every manual adjustment in V1; the proposer cannot self-approve.
- Expected inventory version and resulting non-negative availability are revalidated at execution.
- Adjustments are append-only movements; historical movements are never edited/deleted.
- Duplicate command identity produces one movement/effect.

Trade-off: strong control without inventing a numeric high-impact threshold, but adds approval workload.

#### `STOCK-D2` — Threshold-based direct adjustment

Permit direct adjustments below an owner-defined quantity/value threshold; require approval above it.

Trade-off: faster operations, but cannot be implemented until exact thresholds and valuation basis are approved.

## 6. Quotation validity and order cancellation

### GAP-03-10 — Quotation validity

#### `QVALID-D1` — Configurable default with bounded issue-time selection (recommended)

- Default validity proposal: 30 calendar days from `Sent`.
- Authorized Sales may shorten validity before issue.
- Extending beyond the configured default requires Manager approval and creates approval evidence on that exact revision.
- Once Sent, validity is immutable for that revision; a change requires a new revision.
- Expiration transition is idempotent and cannot override Accepted/Rejected/Converted.

Trade-off: familiar B2B validity period and controlled exceptions. The `30 days` value is a proposed business value and must be explicitly approved.

#### `QVALID-D2` — Per-quotation validity with no default

Require Sales to select validity for every quote, with configured minimum/maximum bounds supplied by owner.

Trade-off: flexible but increases errors and still needs numeric bounds.

### GAP-03-11 — Cancellation, amendment and refund

#### `CANCEL-D1` — Request then authorized decision (recommended, conditional on ORD-L1/D-004/D-005)

- Customer may submit a cancellation request while order is Pending or Confirmed; this is not a direct backend state mutation.
- An eligible Sales/Operations actor decides the request under D-005 scope.
- Once Processing or later, V1 cancellation is denied unless a separately approved Operations exception can safely reverse all inventory/payment/shipping effects.
- No cancellation after committed dispatch. Return/RMA remains outside V1 unless separately approved.
- Approved cancellation releases active reservation idempotently and starts the D-004 void/refund process when payment evidence requires it.
- Placed order lines/pricing/tax/shipping snapshots are not edited in place. V1 has no arbitrary amendment workflow; correction uses cancel-and-recreate only while cancellation is valid.

Trade-off: clear integrity boundary and simpler V1; less flexible after fulfillment begins.

#### `CANCEL-D2` — Staff cancellation through Packed

Allow authorized staff cancellation until Shipping, with compensation orchestration.

Trade-off: more customer flexibility but significantly more inventory/payment/warehouse race complexity.

## 7. Shipping and tax corrections

### GAP-03-12 — Shipment/tracking correction

#### `SHIP-D1` — Append-only correction before terminal delivery (recommended)

- Operations may append a corrected tracking/carrier event before Delivered when authorized by D-005.
- Provider event identity is deduplicated; out-of-order events cannot move the shipment backward.
- Original provider/manual evidence remains in audit; correction never overwrites history silently.
- Reopening Delivered/Completed or changing shipped quantities requires a separately approved exception workflow and is not part of V1.
- D004-D1 single-shipment operational scope applies unless another shipping package is approved.

Trade-off: supports real tracking mistakes while protecting fulfillment truth.

#### `SHIP-D2` — No manual correction

Only provider events can affect tracking.

Trade-off: simpler authorization, but operational incidents may remain unresolved when provider data is wrong.

### GAP-03-13 — Tax recalculation/correction

#### `TAX-D1` — Draft recalculate; issued snapshot corrected by separate evidence (recommended)

- Draft quotation/order totals may be recalculated under the active D-004 tax configuration.
- Issued quotation and placed order tax snapshots are immutable.
- After issue/place, a legally/accounting-required correction is a separate Finance-authorized adjustment/correction document linked to the original; it never silently rewrites history.
- Provider/accounting failures enter reconciliation and cannot corrupt order/payment truth.
- Exact document type, provider and timing remain D004-B owner inputs.

Trade-off: strong audit/accounting integrity, with additional correction workflow when needed.

#### `TAX-D2` — Recalculate issued snapshots in place

Trade-off: simpler tables but destroys historical evidence and conflicts with approved immutability; not recommended.

## 8. Approval record

| Scope | Selected options | Decision | Approver | Date | Conditions |
| --- | --- | --- | --- | --- | --- |
| GAP-03-01/02/06–13 recommended bundle | `QTE-L1`, `ORD-L1`, `CRM-D1`, `PRIV-D1`, `PROMO-D1`, `STOCK-D1`, `QVALID-D1`, `CANCEL-D1`, `SHIP-D1`, `TAX-D1` | `APPROVE` | Product Owner (user), selection `1` | 2026-08-23 | D-004/D-005/D-006 dependencies remain explicit; no schema/public contract/provider approval |

## 9. Approval evidence and scope

The user replied `1` after being asked to approve item 1, the recommended Step 03 bundle. It is recorded as the following scoped approval:

```text
Tôi phê duyệt Step 03 recommended bundle:
QTE-L1, ORD-L1, CRM-D1, PRIV-D1, PROMO-D1, STOCK-D1,
QVALID-D1, CANCEL-D1, SHIP-D1, TAX-D1.

Các phần phụ thuộc D-004, D-005, D-006 vẫn chưa được coi là approved
cho đến khi decision tương ứng hoàn tất.
```

The matrix was revised row-by-row and rechecked on 2026-08-23. Step 03 becomes `DONE` only after D-004/D-005, all conditional inputs and complete Product/Architecture approval are recorded.

# D-003 — V1 Discount and Quotation Approval Policy

## 1. Decision control

- Status: `APPROVED — D-004 BASIS CONFIRMED`
- Decision ID: `D-003`
- Approved by: Product Owner (user)
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval evidence: the user selected option `1` for tiered authority and then option `1` for the balanced numeric package
- Applies to: V1 manual pricing overrides, negotiated quotation prices, quotation issue approval and revision behavior
- Does not approve: payment/tax/shipping components of quotation total (`D-004`), role/resource scope (`D-005`), product cost fields, schema, API or event contracts

## 2. Approved authority model

- Sales may apply a manual discount or negotiated quotation price only within the Sales authority limit configured for the relevant scope.
- A request exceeding the Sales limit requires an eligible Manager approval.
- A request exceeding the Manager limit, or matching an approved financial-risk criterion, requires an eligible Finance approval.
- A request exceeding every configured authority tier fails closed and cannot be issued until an authorized policy decision covers it.
- Administrator status is not an approval bypass. Only the effective server-side permission, resource scope and configured authority tier count.
- UI visibility, client assertions and AI output cannot grant or raise discount authority.

## 3. Separation of duties

- A proposer cannot approve the portion of a request that exceeds the proposer's own direct authority.
- The required approver must be currently authorized for the quotation/customer/company scope under D-005.
- Approval is an explicit decision over the exact normalized quotation revision and its calculated financial values; silence or workflow timeout is not approval.
- Authorization and thresholds are revalidated when approval is recorded and again before the approved revision is issued or converted.

## 4. Revision and approval validity

- Approval is bound to one immutable quotation revision and its integrity hash/version.
- Changing product, quantity, unit price, discount or commercial terms after approval invalidates that approval and requires a new quotation revision and fresh evaluation.
- An issued revision is never edited in place. A change creates a new draft revision linked to the prior revision.
- An accepted revision remains immutable and cannot receive a retroactive discount or replacement approval.
- Duplicate approval commands for the same revision/decision identity return the existing outcome; conflicting or stale decisions fail closed.

## 5. Approved limits and escalation

The required approval tier is the highest tier triggered by discount percentage, quotation value or financial-risk criteria:

| Trigger | Required authority |
| --- | --- |
| Manual discount up to and including `5%`, and no higher trigger applies | Sales direct authority |
| Manual discount above `5%` and up to and including `15%` | Manager approval |
| Manual discount above `15%` and up to and including `25%` | Finance approval |
| Manual discount above `25%` | Reject; V1 has no exceptional bypass |
| Approved quotation total from `100,000,000 VND` inclusive and below `500,000,000 VND` | At least Manager approval |
| Approved quotation total from `500,000,000 VND` inclusive | Finance approval |
| Effective selling price below the approved product cost basis | Finance approval regardless of percentage/value tier |

- Discount percentage is the manual reduction measured against the authoritative price resolved by D-001 before the manual negotiated override.
- D-004 confirms VND-only for V1; the approved thresholds therefore apply as written. Any future currency change requires an approved D-003 revision and is never auto-converted.
- “Quotation total” is the D-004 final payable total: merchandise after approved discount plus VAT plus shipping.
- Cost basis must come from an approved authoritative/configured source. Missing or stale cost evidence fails closed for the below-cost check; it cannot be guessed.
- Limits and their effective revision are configuration/business data and must not be duplicated as hard-coded role conditionals.

## 6. Required verification

- Boundary tests cover immediately below, equal to and above `5%`, `15%`, `25%`, `100,000,000 VND` and `500,000,000 VND`.
- Permission tests cover Sales, Manager, Finance, Administrator and cross-scope actors through direct server requests.
- Tests prove a proposer cannot self-approve an over-limit request.
- Tests prove required Finance cases cannot be issued with Manager approval alone.
- Revision tests prove financial/terms changes invalidate prior approval and never mutate the approved/issued revision.
- Stale, duplicate and conflicting approval commands are deterministic and idempotent.
- Configuration revision changes do not rewrite historical quotation approval evidence.

## 7. Consequences and follow-up gates

- The tiering, limits, separation-of-duties and revision rules may be used to reconcile proposed pricing/quotation rules.
- D-004 confirms VND and the authoritative final-payable-total composition; this condition is closed.
- D-005 must define the server-side resource scope and effective permissions for Sales, Manager and Finance actors.
- Step 03 remains blocked by D-004, D-005 and final Product/Architecture approval of the complete Business Rules Matrix.

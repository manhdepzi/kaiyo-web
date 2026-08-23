# Step 04 System Architecture Revalidation Report

- Status: `DONE — APPROVED V1 BASELINE`
- Revalidation date: 2026-08-23 (Asia/Bangkok)
- Architecture under review: [System Architecture — Approved V1 Baseline](./system-architecture.md)
- Approved inputs: [Product Requirements v0.1](../product/product-requirements.md), [V1/V2 Scope Matrix v0.1](../product/scope-matrix.md), [D-001](../product/decisions/D-001-pricing-policy.md) through [D-007](../product/decisions/D-007-runtime-environment-provider-policy.md), and the approved [Step 03 matrix](../business/business-rules-matrix.md)
- External inputs retained as downstream gates: legal retention/residency, real forecast and concrete provider/account bindings

## 1. Outcome

The proposed architecture remains compatible with the approved product boundary: V1 is a single-tenant Modular Monolith for commerce/B2B/CRM/CMS/SEO/operations, while AI Platform is V2 and is not a dependency of the V1 commerce core.

Step 04 is approved at V1 baseline level. The following remain downstream contract/production inputs and do not authorize provider binding or destructive operations:

1. Legal retention/residency and the real business forecast under D-006.
2. Concrete provider/account/region/budget contracts under D-007.
3. Database/API/Event/permission contracts in Steps 07–08/13/48.
4. Accepted ADRs for material technology decisions in Step 09.

No schema, migration, public API/event contract, provider purchase or application scaffold is authorized by this report.

## 2. Revalidation matrix

| Architecture concern | Evidence checked | Result | Required follow-up |
| --- | --- | --- | --- |
| V1/V2 boundary | Approved scope matrix; architecture principles 9; module diagram; AI fitness functions | `PASS — DESIGN` | Preserve an automated dependency rule when code exists |
| V1 single tenancy | Approved scope matrix | `PASS — PRODUCT BOUNDARY` | Record tenancy as an accepted ADR before persistence design |
| Modular Monolith | Governance; architecture sections 3, 6 and 15 | `PASS — DESIGN` | Step 05 owns detailed boundaries; extraction requires evidence and ADR |
| MySQL authority | Architecture sections 3, 5, 7 and 9 | `PASS — DESIGN` | Concrete constraints, locks and isolation belong to Steps 06–07 |
| Redis/search as derived stores | Architecture sections 5, 9 and 16 | `PASS — DESIGN` | D-006 freshness budget approved; concrete adapter Step 09 |
| Pricing boundary | D-001/D-003/D-004 and approved matrix | `PASS — POLICY ALIGNED` | Step 07 defines representation |
| Inventory boundary | D-002/D-004 and critical-write flow | `PASS — POLICY ALIGNED` | Steps 06–07 define state/locking contracts |
| Approval authority | D-003/D-005 and server-side authorization | `PASS — POLICY ALIGNED` | Exact permission identifiers Step 13 |
| Tax/payment/shipping | D-004 ports/adapters and data authority | `PASS — PROVIDER NEUTRAL` | Concrete providers/contracts remain affected integration gates |
| Identity/RBAC | D-005 and server-side authorization principle | `PASS — POLICY ALIGNED` | Permission catalog Step 13 |
| NFR/operations | D006-A2 and D007-A1 | `PASS — ENGINEERING BASELINE` | Legal/real forecast/provider accounts remain production inputs |
| SSR/SEO/accessibility | SSR principle and D-006 mapping | `PASS` | WCAG 2.2 AA and CWV targets approved |
| Reliable side effects | Transaction plus reliable-dispatch proposal | `PASS — INVARIANT` | Select the concrete pattern through ADR after contracts are known |
| Public contracts | Architecture explicitly avoids naming payloads/endpoints | `PASS — GOVERNANCE` | Define only in Steps 08 and 48 after upstream approvals |

## 3. Dependency review

The proposed module graph is directionally safe for the approved scope:

- Delivery depends on application interfaces, not persistence internals.
- Quote and Order consume Pricing/Catalog/CRM/Tax capabilities through explicit boundaries.
- Order coordinates Inventory, Payment and Shipping but does not make Redis, search or AI authoritative.
- AI orchestration depends on approved application ports; Order, Inventory and Payment have forbidden dependencies on AI.
- Provider SDKs stay in infrastructure adapters.

The detailed ownership graph is now approved in [Step 05 Domain Boundaries](./domain-boundaries.md). Step 06 may use it to model entities/cardinalities, while table/field/migration decisions remain prohibited until Steps 06–07 approve them.

## 4. Failure-mode review

| Failure | Proposed safe behavior | Revalidation |
| --- | --- | --- |
| Duplicate/retried command | Durable idempotency identity and stored outcome in MySQL | Compatible with D-001–D-003 |
| Process crash after commit | Committed reliable-dispatch record relayed after commit | Pattern choice remains ADR work |
| Duplicate/out-of-order job | At-least-once handler with durable deduplication/reconciliation | Required invariant retained |
| Payment/carrier timeout or unknown result | Bounded timeout/backoff; persist unknown/reconciliation state; do not guess success | D-004 policy approved; concrete adapter contract later |
| Redis/search outage | Commerce truth remains in MySQL; degrade cache/search capability | Compatible with governance |
| AI/provider outage | V1 checkout, payment and inventory continue with AI disabled/unreachable | Compatible with approved V2 boundary |
| Concurrent price/stock mutation | Reprice/revalidate; transactional lock/version check; immutable committed snapshot | Concrete isolation/lock contract belongs to Steps 06–07 |
| Unauthorized direct request | Server-side policy and D-005 resource-scope check regardless of UI | Exact permission identifiers Step 13 |

## 5. Downstream decisions still requiring contracts/inputs

| Gate | Required decision | Impact if unresolved |
| --- | --- | --- |
| Legal/privacy input | Retention/residency/legal holds | Automatic destructive privacy execution remains disabled/fail closed |
| Real forecast | Actual traffic/data/cost forecast | May raise qualification/capacity targets; cannot silently lower them |
| Provider bindings | Accounts, regions, budgets and signed contracts | Real integrations and production deployment stay disabled |
| Steps 06–08/13/48 | DB/API/permission/event contracts | No migration/public endpoint/event/permission identifier before approval |
| ADR set | Tenancy, runtime, DB, Redis/queue, storage, search, API versioning and reliable dispatch | Step 09 records the approved baseline and compatibility/rollback decisions |

## 6. Step 04 exit criteria

Step 04 may move to `DONE` only when all of the following are evidenced:

- Step 03 is approved and every critical rule used by the architecture is traceable.
- D-004/D-005 policy, D006-A2 and D007-A1/runtime baseline are approved with owner/date/conditions.
- Context, container, module ownership, data authority, failure modes and NFR mapping are reconciled to those decisions.
- Commerce-with-AI-disabled is retained as an explicit architecture fitness function.
- Material technology choices are identified for Step 09 ADR recording before Step 11.
- The Architecture approver records approval; an existing file or proposal-only `PASS` is not sufficient.

## 7. Current disposition

`DONE — APPROVED V1 BASELINE` is the correct status for Step 04. The next execution path is:

1. Produce the Step 05 domain ownership/boundary map from the approved matrix.
2. Produce ERD/schema/API/Event proposals and approve their contracts in sequence.
3. Record material choices as accepted ADRs.
4. Complete coding standards and early testing/security/observability/DR controls before Step 11.

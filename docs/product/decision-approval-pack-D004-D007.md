# Kaiyo Web — Decision Approval Pack D-004 to D-007

## 1. Document control

- Status: `PROPOSED — NOT APPROVED`
- Created: 2026-08-23 (Asia/Bangkok)
- Purpose: consolidate the remaining V1 business, authorization, NFR and environment decisions so Product/Architecture owners can approve them in one review
- Boundary: this file proposes choices; it does not approve rules, providers, spend, schema, permissions, public contracts, production access or destructive operations
- Upstream approved decisions:
  - [D-001 Pricing Policy](./decisions/D-001-pricing-policy.md)
  - [D-002 Inventory Reservation Policy](./decisions/D-002-inventory-reservation-policy.md)
  - [D-003 Discount and Quotation Approval Policy](./decisions/D-003-discount-quotation-approval-policy.md)

Primary trace:

- Architecture/Functional Specification v1.1 §§2, 5, 9, 15, 23.8–23.10, 23.15, 23.18–23.22 and 24.7–24.8, 24.18, 24.20, 24.22–24.26.
- [Product Requirements v0.1](./product-requirements.md).
- [Execution Master Plan](../../planManh.md), Decision Register.

## 2. How to approve

Each decision has a recommended package and explicit unresolved inputs. Approval must identify package IDs and fill every field marked `OWNER INPUT REQUIRED`. A package may be changed before approval. Silence is not approval.

Suggested response format:

```text
D-004: approve D004-A1, D004-B1, D004-C1, D004-D1; [fill required inputs]
D-005: approve D005-A1
D-006: approve D006-A2; [fill traffic and retention inputs]
D-007: approve D007-A1 and runtime baseline; [fill provider/account inputs or explicitly defer adapters]
```

## 3. D-004 — VAT, invoice, payment, reservation TTL, refund and shipping

### D004-A — Currency, VAT and quotation/order total

#### `D004-A1` — Net VAT / VND-only (recommended)

- V1 transactional currency is VND only; multi-currency is out of V1.
- D-001 resolves the authoritative unit price before manual discount.
- Calculate merchandise subtotal, subtract approved discount, calculate VAT separately from an approved tax classification, then add shipping.
- `final payable total = merchandise after discount + VAT + shipping`.
- D-003 quotation-value thresholds compare against final payable total.
- Issued quotation/order snapshots unit price, discount, VAT calculation/classification, shipping and totals.
- Tax classifications and rates are versioned configuration; no tax rate is hard-coded.

Trade-off: clearest B2B/accounting trace and no reverse-VAT rounding, but public UI must deliberately display the customer-payable VAT-inclusive amount where required.

#### `D004-A2` — VAT-inclusive authoritative prices

Store/resolve customer-facing prices as VAT-inclusive and derive the VAT component for documents.

Trade-off: simpler public display, but reverse calculation and rounding reconciliation are more complex for quotation/order lines.

#### `D004-A3` — No VAT in V1

Only valid if an authorized Finance/legal owner confirms the business is outside the applicable VAT requirement.

Trade-off: simplest implementation, but unsafe as a default and cannot be selected by Engineering.

`OWNER INPUT REQUIRED`: approved tax classifications/rates and effective-date source. Legal/tax applicability must be confirmed by the business; this document is not tax advice.

### D004-B — Invoice behavior

#### `D004-B1` — Method-aware invoice eligibility (recommended)

- Checkout/quotation records whether an invoice is requested and snapshots approved company name, tax code, billing address and invoice recipient details.
- Prepaid gateway: eligible for invoice workflow only after verified settlement.
- Bank transfer/B2B terms: eligible only after Finance verifies the required settlement/term condition.
- COD: eligible only after authoritative delivery/payment confirmation.
- Invoice issuance is a Finance/accounting action or approved accounting-provider integration; client/order status alone cannot assert issuance.
- Correction/cancellation never rewrites the historical order snapshot; it follows a separately audited accounting action.

Trade-off: consistent with actual payment evidence but requires method-specific states and reconciliation.

#### `D004-B2` — Manual Finance-only invoice workflow

All requested invoices enter a Finance queue; no automated issuance trigger in V1.

Trade-off: lower integration risk, higher operational workload and slower issuance.

`OWNER INPUT REQUIRED`: invoice provider/process and legally required issue/correction timing. No provider API may be implemented without its approved contract.

### D004-C — Payment methods, TTL and refunds

#### `D004-C1` — Full-payment V1 with provider abstraction (recommended)

- B2C supports COD, bank transfer and one approved online gateway.
- B2B supports bank transfer or an explicitly approved payment term; deposit and installment/partial-settlement collection are deferred from V1, while the payment model remains extensible.
- Gateway reservation TTL proposal: 30 minutes while awaiting verified payment.
- Bank-transfer reservation TTL proposal: 24 hours while awaiting Finance verification.
- COD and approved B2B terms do not auto-expire after server-side order/payment-term confirmation, consistent with D-002.
- Failed/expired payment releases reservation idempotently; late callbacks enter reconciliation and never resurrect a released reservation implicitly.
- V1 supports full refunds. Partial refunds require separate approval (`D004-C2`) rather than being inferred from architecture readiness.

Trade-off: supports common V1 flows with bounded inventory holds; excluding deposit/partial payment reduces ledger and reconciliation complexity.

#### `D004-C2` — Add deposit and partial payment/refund in V1

Add multiple settlements, remaining balance, deposit rules and amount-based partial refunds.

Trade-off: better B2B flexibility, but materially expands accounting states, reconciliation, authorization and concurrency testing.

#### `D004-C3` — COD and bank transfer only

Defer online gateway and webhook integration.

Trade-off: fastest launch, but weaker B2C conversion and more manual reconciliation.

`OWNER INPUT REQUIRED`: approve/replace the proposed TTLs; choose payment package; name the online gateway only if enabled; define B2B payment-term choices and full-refund eligibility/cutoff.

### D004-D — Shipping

#### `D004-D1` — Configured B2C + manual B2B, single-shipment V1 (recommended)

- B2C uses approved configured shipping methods/fees and optional free-shipping conditions.
- B2B permits manual shipping fee or shipping included in the approved quotation.
- Shipping address, billing address, method, fee, terms and delivery note are snapshotted on issued quotation/order.
- V1 operational flow creates one shipment per order. Data/domain boundaries must not prevent a later split-shipment requirement, but split fulfillment UI/workflow is not implemented in V1.
- Carrier tracking is behind an adapter and queued; carrier outage never changes order/payment/inventory truth or blocks core order creation.

Trade-off: production-capable manual operation with limited integration complexity.

#### `D004-D2` — Carrier-rated B2C in V1

Fetch live carrier rates and tracking through one provider adapter, with timeout/fallback and queued synchronization.

Trade-off: better automation, but creates checkout availability, pricing and reconciliation dependencies.

#### `D004-D3` — Manual shipping only

All shipping fee/tracking updates are Operations-entered.

Trade-off: fastest implementation, highest manual workload.

`OWNER INPUT REQUIRED`: shipping methods, fee/free-shipping rules, service geography, carrier/provider if any, and whether D004-D1 single-shipment V1 is accepted.

## 4. D-005 — Identity, ownership, permissions, delegation and break-glass

### `D005-A1` — Permission-first scoped RBAC (recommended)

Permission is authorization truth; roles are configurable bundles. Every protected request is authorized server-side and deny-by-default.

| Bundle | Proposed V1 resource scope |
| --- | --- |
| Customer | Own profile/addresses/orders/quotes; Company resources only through active membership and an allowed company capability |
| Sales | Assigned Leads, Customers, Companies, quotations and related orders/tasks only; D-003 direct authority applies |
| Sales Manager | Own plus assigned team scope; D-003 Manager approvals in that scope |
| Warehouse | Inventory and fulfillment actions for assigned operational scope; no pricing/payment/RBAC management |
| Finance | Payment/refund/invoice actions and D-003 Finance approvals in permitted scope; no inventory/RBAC bypass |
| Admin | Only explicitly assigned global/module permissions; label `Admin` is not a bypass |
| Super Admin | Access-policy/system administration bundle; business mutations still require explicit permissions and business validation |

Additional rules:

- A Company may have multiple active members/contacts. Membership alone does not grant every company capability.
- Sales ownership is assigned to Customer or Company. Reassignment is explicit, audited and takes effect for subsequent authorization checks.
- Sales Manager team scope is derived from an approved reporting/assignment relation, not client input.
- No actor may grant an authority they are not permitted to delegate.
- Revocation is authoritative in MySQL; cache failure cannot extend high-impact permission.
- Break-glass is a separate time-bounded authorization requiring strong authentication, reason, eligible approver, immediate alert and post-use review. It never creates a permanent role grant.

Trade-off: strongest least-privilege model and scalable role bundles, with more policy tests and explicit assignments.

### `D005-A2` — Global staff access by role

Sales/Manager/Admin bundles see all records in their module.

Trade-off: simpler, but conflicts with the approved scoped-access direction and increases privacy/business risk; not recommended.

`OWNER INPUT REQUIRED`: approve D005-A1 and identify who may manage role/permission assignments, approve break-glass, manage Sales teams, reassign ownership and administer Company member capabilities. Exact public permission names are produced only after this policy is approved.

## 5. D-006 — NFR, SLO, performance, recovery, accessibility and retention

### Non-negotiable standards proposed for approval

- Accessibility target: WCAG 2.2 Level AA for V1 release surfaces.
- Field Core Web Vitals target at the 75th percentile: LCP ≤2.5s, INP ≤200ms and CLS ≤0.1.
- No N+1 on critical/listing paths; every high-volume query has an approved access path/index and measured query plan.
- No capacity claim is approved without production-like load data and a declared traffic/data model.

References: [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/) and [Google Core Web Vitals guidance](https://developers.google.com/search/docs/appearance/core-web-vitals).

### Availability/recovery packages

| Package | Monthly availability target | RPO | RTO | Trade-off |
| --- | --- | --- | --- | --- |
| `D006-A1` Lean | 99.9% | 1 hour | 4 hours | Lower operating cost, larger recovery window |
| `D006-A2` Balanced (recommended) | 99.95% | 15 minutes | 2 hours | Requires managed/replicated data and frequent recoverable logs/backups |
| `D006-A3` Critical | 99.99% | 5 minutes | 30 minutes | Higher infrastructure/operations cost and more complex failover drills |

Backup baseline from the specification: daily plus weekly backups, encrypted/scoped, off-site or failure-domain independent, with scheduled restore tests. Backup without a successful integrity-checked restore is not complete.

### `OWNER INPUT REQUIRED` traffic/data model

- expected and campaign peak requests/second;
- daily sessions and orders/quotations;
- concurrent checkout/payment attempts;
- SKU/variant/customer/order sizes at launch and planning horizon;
- import/export/Merchant batch sizes;
- allowed maintenance window and business-critical hours.

### `OWNER INPUT REQUIRED` retention/residency

Provide required retention and deletion/anonymization rules for customer/contact/company/address, order/invoice/payment, quotation, audit, IP/device/session, application logs and backups. Identify required data residency. Legal/accounting retention cannot be guessed by Engineering.

## 6. D-007 — Runtime, environments and external providers

### Runtime baseline proposed for approval

| Component | Recommended baseline | Reason/evidence |
| --- | --- | --- |
| Laravel | 13.x (`^13.0`) | Current major; security fixes through 2028-03-17 per official support table |
| PHP | 8.5 latest patch | Active support through 2027-12-31 and security support through 2029-12-31 |
| MySQL | 8.4 LTS latest patch | LTS behavior/support track; authoritative commerce datastore |
| Redis | 8.2 latest patch | Published GA line with EOL 2030-09-01; derived cache/session/rate-limit/queue/lock only |
| Frontend | Blade SSR + Livewire/Alpine/Tailwind through locked compatible packages | Matches approved SSR/Modular Monolith direction; exact package versions belong in lockfiles |

Primary references: [Laravel 13 release/support policy](https://laravel.com/docs/13.x/releases), [PHP supported versions](https://www.php.net/supported-versions.php), [MySQL 8.4 LTS release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html), [Redis version management](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/).

### `D007-A1` — Provider-neutral managed production (recommended)

- Development/CI is reproducible and does not rely on undocumented Laragon state.
- Production uses stateless Linux Laravel web/worker roles behind TLS/CDN/WAF/load balancing.
- MySQL, Redis, object storage and backup use managed or operationally owned services that can meet approved D-006 targets.
- Search starts with the database/full-text adapter only if measured data/query requirements pass; external search is not selected speculatively.
- Payment, carrier, tax/invoice, email/SMS and observability integrations use ports/adapters with timeout, bounded retry, idempotency and reconciliation.
- Staging and production have isolated secrets/data/resources. Production credentials never enter the repository.

Trade-off: portable architecture with lower lock-in, but concrete deployment/runbooks cannot close until providers/accounts are named.

### `D007-A2` — Self-managed single host

Run web, workers, MySQL and Redis on one owned server with off-site backup.

Trade-off: lower initial spend, but weaker isolation/availability and unlikely to meet aggressive D006-A2/A3 targets without additional topology.

### `OWNER INPUT REQUIRED` environment/provider contracts

- cloud/hosting provider, region and approved monthly budget;
- DNS/CDN/WAF/TLS provider and account authority;
- MySQL/Redis/object-storage/backup services;
- payment gateway, bank-transfer reconciliation method and invoice provider;
- carrier/shipping integration, if D004-D2 is selected;
- transactional email/SMS provider and sending domains;
- error tracking, logs/metrics/traces and uptime monitoring providers;
- secret-management mechanism and staging/production access owners.

An integration may be explicitly deferred; the affected feature remains blocked and must degrade safely. No account creation, purchase, external message, DNS change or production deployment is authorized by approving this architecture package alone.

## 7. Approval status

| Decision | Status | Blocking remainder |
| --- | --- | --- |
| D-004 | `APPROVED POLICY` | `A1+B1+C1+D1`; concrete rates/fees/providers required before affected capability enablement |
| D-005 | `APPROVED POLICY` | `A1`; exact permission catalog is a Step 13 contract |
| D-006 | `PARTIALLY APPROVED` | A2 and engineering profile approved; legal retention/residency and real forecast remain external inputs |
| D-007 | `APPROVED BASELINE` | Runtime and A1 topology approved; concrete provider/account bindings remain external inputs |

The individual records are authoritative for their approved scope. Missing provider/account/legal inputs continue to block only their affected capability or production gate. Schema, public contracts and Laravel scaffolding still require the applicable numbered design steps.

Individual decision drafts:

- [D-004 Commerce/Finance/Operations](./decisions/D-004-commerce-finance-operations-policy.md)
- [D-005 Identity/Authorization](./decisions/D-005-identity-authorization-policy.md)
- [D-006 NFR/SLO/Recovery](./decisions/D-006-nfr-slo-recovery-policy.md)
- [D-007 Runtime/Environment/Providers](./decisions/D-007-runtime-environment-provider-policy.md)

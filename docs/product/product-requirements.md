# Kaiyo Web Product Requirements Document

## 1. Document control

- Promptbook step: `02 — Product Requirements & Scope`
- Status: `APPROVED`
- Version: `0.1`
- Created: 2026-08-23 (Asia/Bangkok)
- Release focus: V1 Commerce/B2B/CRM/CMS/SEO; AI Platform deferred to V2
- Scope companion: [V1/V2 Scope Matrix](./scope-matrix.md)
- Source baseline:
  - [Architecture & Functional Specification v1.1](../../Kaiyo-web/Website_Ecommerce_B2B_SEO_Architecture_Functional_Specification_v1.1_Production_Optimized.docx)
  - [AI Enterprise Full-Stack Engineering Promptbook v2.0](../../Kaiyo-web/AI_Enterprise_FullStack_Engineering_Promptbook_v2.0.docx)
  - [Execution Master Plan](../../planManh.md)

This document normalizes supplied requirements; it does not approve unresolved business rules, schema, permissions, provider contracts or numeric NFR targets.

## 2. Product objective

Kaiyo Web is a single operational platform for:

- B2C product discovery, cart, checkout, payment, fulfillment and account history.
- B2B customer/company management, secure quotation, negotiation/revision, approval and quote-to-order.
- Sales CRM operations for leads, customers, companies, follow-up, quotations, assigned orders and performance.
- Administration of commerce, content, SEO, Merchant distribution, analytics and system operations.
- Production operation with server-side authorization, auditability, transaction integrity, retry safety, observability, backup/restore and controlled deployment.

V1 must remain fully operable without AI. AI capabilities are a separate V2 bounded context and release gate.

## 3. Product decisions currently established by the execution plan

| Decision | V1 requirement | Status |
| --- | --- | --- |
| Release scope | Commerce, B2B, CRM, CMS, SEO, Merchant/Analytics and operations | APPROVED for V1 |
| Tenancy | One business/operator tenant in V1; do not hard-code assumptions that make a future tenancy ADR impossible | APPROVED for V1 |
| AI | Steps 36–47 are V2; V1 has no runtime dependency on AI/LLM | APPROVED release boundary; architecture/tests must enforce |
| Architecture direction | Modular Monolith; service extraction only with evidence and ADR | GOVERNANCE CONSTRAINT |
| Data authority | MySQL is commerce truth; Redis/search are derived | GOVERNANCE CONSTRAINT |

## 4. Personas and jobs-to-be-done

| Persona | Jobs-to-be-done in V1 | Key boundaries |
| --- | --- | --- |
| Guest | Discover/search/filter products and content; use cart; request and securely access a quotation; contact the business | Guest is not a DB role; protected records cannot be accessed by guessable IDs |
| Registered User | Manage profile/addresses/company relationship; buy products; track orders/shipments; manage quotes, wishlist, reviews and notifications | May access only authorized own/company resources |
| Business Customer | Represent an approved Company/contact context; request negotiated/tier quotation; provide invoice/delivery information; convert accepted quote through approved flow | Company membership, contact authority, pricing and payment terms require approved rules |
| Sales | Manage assigned leads/customers/companies, quotations, follow-ups/tasks and assigned orders; view scoped performance | Server permission and ownership scope apply; discounts/approvals follow limits |
| Sales Manager | Review scoped Sales activity and approve actions within an approved authority matrix | Exact role bundle/limits are not yet approved |
| Administrator | Operate permitted commerce, Sales, CMS, SEO, Merchant, analytics and system functions | “Admin” is not an authorization bypass; permissions remain authoritative |
| Warehouse/Operations | Manage approved stock, fulfillment and shipping actions | Exact role/scope and transition authority are pending D-005 |
| Finance | Review payment/refund/invoice/financial exceptions within approved authority | Exact role/limits and separation of duties are pending D-003/D-005 |

## 5. V1 functional requirements

### 5.1 Identity, account and authorization

- One authentication system serves Customer, Sales and Admin experiences and redirects by server-side permission.
- Support registration/login, verification/reset, secure session/device management, account disable/revoke and login history/throttling.
- Staff protection includes approved 2FA/password/session policy.
- Permissions, not UI visibility, are authorization truth; role bundles remain configurable.
- Guest is an unauthenticated state, not a persisted role.

### 5.2 CRM and B2B identity

- Separate login identity (`User`), commerce/CRM profile (`Customer`), business entity (`Company`) and contact/membership relationship.
- Manage Leads, Customer/Company ownership, contacts, attribution, notes/tasks/follow-up and duplicate-safe conversion.
- Existing identified Customer/Company requests must not create duplicate Leads by default; conversion/duplicate rules require approval.
- Sales sees and acts only within approved ownership/resource scope.

### 5.3 Catalog, media and search

- Manage categories, brands, products, variants, attributes, technical specifications, media/documents and sellability/status.
- Product/variant identifiers and URLs follow approved uniqueness and redirect/indexation rules.
- Public discovery supports category/brand/product/search pages, name/SKU search, filter/sort and responsive pagination/load-more.
- Search is accessed through an adapter. V1 may use DB/full-text only if measured requirements are met; source of truth remains MySQL.
- Uploads use quarantine, MIME/content/size validation, safe names/storage and a malware-scan hook.

### 5.4 Pricing

- One deterministic PricingEngine resolves approved retail, promotion, B2B tier/contract, customer/company override and quotation-negotiated inputs.
- UI, controllers and AI cannot calculate or override authoritative price.
- Issued quotations and orders snapshot unit price, discounts, tax, shipping and price source/revision.
- Pricing precedence, non-stacking and rounding are approved in [D-001](./decisions/D-001-pricing-policy.md). [D-003](./decisions/D-003-discount-quotation-approval-policy.md) approves tiered Sales → Manager → Finance authority, limits, separation of duties and revision invalidation; VND and authoritative quotation-total composition remain conditional on D-004.

### 5.5 Inventory

- V1 may operate one warehouse but uses warehouse-ready stock, movement and reservation concepts.
- Track on-hand, reserved and available values from authoritative MySQL records.
- Reserve/release/commit operations are transactional, concurrency-safe and idempotent; V1 does not permit backorders or negative available stock.
- [D-002](./decisions/D-002-inventory-reservation-policy.md) approves reserve on B2C order creation and B2B quote-to-order conversion, release on cancellation/payment-method-based expiry, and commit on dispatch confirmation. Auto-expiry applies only while awaiting payment/approved confirmation, stops after server verification, and has no manual renewal. Concrete payment methods and TTL values remain D-004.

### 5.6 Cart and checkout

- Guest cart persists through an approved browser/session identifier and merges deterministically after login.
- Checkout validates identity/customer context, address, shipping, price, tax and stock against current authoritative data.
- Final price/availability cannot rely on stale cart/cache values.
- Repeated/concurrent submission must create one authoritative result and no partial order/payment/inventory state.

### 5.7 Order, payment and shipping

- Orders use an approved state machine, immutable financial/delivery snapshots, audit and permission-controlled transitions.
- Cancellation/refund coordinates order, payment and inventory effects; no client may set backend state directly.
- Payment business records are separate from provider attempts/events/refunds; callbacks are authenticated and idempotent, and unknown outcomes enter reconciliation.
- V1 supports only approved COD/bank-transfer/gateway and B2B payment terms; partial/deposit behavior remains D-004.
- Shipping supports approved manual B2B methods and a replaceable carrier abstraction; slow/outage carrier calls cannot block core commerce truth.

### 5.8 Quotation and quote-to-order

- Guest/User may request quotation; guest submission includes rate limiting, risk-based bot protection, validation, duplicate review and secure access.
- Quotation uses the specification lifecycle and immutable revisions; exact state/actor matrix is approved in Step 03.
- Sales may edit draft/revision products, quantities, price inputs, discount, terms and expiry only within permission/approval rules.
- An accepted revision cannot be edited in place.
- Conversion creates exactly one order transactionally and follows approved inventory/payment rules.

### 5.9 Customer, Sales and Admin experiences

- Customer portal covers profile, addresses/company, orders/tracking, quotations, wishlist, reviews, notifications and security.
- Sales portal covers dashboard, leads, Customer 360, companies, quotations, orders, tasks/follow-up, notifications, search and scoped performance.
- Admin covers approved Commerce, Sales, CMS, SEO, Merchant, Analytics and System operations with drill-down, filters, async import/export, audit and settings.
- Every protected action is authorized server-side; every large listing uses server pagination/filtering and reviewed query paths.

### 5.10 CMS, SEO, Merchant and analytics

- CMS manages Page/Article/FAQ/Banner content with Draft→Review→Scheduled→Published→Archived workflow.
- SEO-critical pages render primary content/metadata in initial server HTML.
- Support approved canonical, robots, XML sitemap, redirect, 404/410, faceted noindex and explicit SEO landing rules.
- Structured data is emitted only when source data supports it; no invented Product/Offer/Review values.
- Merchant lifecycle is asynchronous and uses the same authoritative product/price/availability sources.
- Analytics covers approved B2C/B2B/contact events and attribution without turning MySQL into a GA clone; events are deduplicated.

### 5.11 Notifications, audit and operations

- Retry-safe in-app/email/browser notifications cover approved commerce, quote, CRM, Merchant and SEO events.
- Critical/security transactional notifications cannot be disabled where policy requires them.
- Audit captures actor, action, object, before/after or decision evidence, time and correlation without leaking secrets/PII.
- Production readiness includes CI/CD, monitoring/alerts, failed-job handling, backups with tested restore and verified rollback/deployment runbooks.

## 6. AI/V2 requirements boundary

- AI Platform is not a V1 feature dependency.
- V2 may include provider-neutral LLM gateway/model registry, prompt management, conversation/request trace, RAG, tool/agent runtime, Sales/Support/Quotation assistants, evaluation, cost/reliability and AI security.
- V2 starts only after V1 production launch and D-008 approval.
- AI output is untrusted; model/tool access never grants authorization.
- AI write tools default disabled. High-impact writes require immutable proposal, server policy/validation, current authorization, idempotency and human approval.
- Catalog, cart, manual quotation, checkout, order, payment and inventory must pass E2E with all AI flags off and providers unreachable.

## 7. Non-functional requirements

| NFR | V1 requirement | Target status |
| --- | --- | --- |
| Integrity | MySQL constraints/transactions protect authoritative state; critical retries are idempotent | REQUIRED; exact contracts pending |
| Security | Server authz, CSRF/XSS/mass-assignment/rate/session/webhook/upload/secrets/PII controls | REQUIRED; threat model pending |
| Availability/resilience | Stateless web roles; optional integrations degrade; external calls timeout/backoff; failed work observable | REQUIRED; numeric SLO pending D-006 |
| Performance | No N+1; indexes follow access paths; SSR/assets/images/cache/queues designed to approved budgets | REQUIRED; numeric budgets/traffic pending D-006 |
| Scalability | Modular Monolith and replaceable adapters; scale from telemetry/load evidence, not speculative services | REQUIRED |
| SEO | Critical content SSR; correct crawl/index/canonical/schema behavior | REQUIRED; page inventory pending |
| Accessibility | Keyboard/focus/semantics/contrast/form errors/alt/reduced-motion | REQUIRED; conformance target pending D-006 |
| Privacy | Data minimization, scoped access, redaction and approved retention/export/anonymization | REQUIRED; retention/residency pending D-006 |
| Observability | Correlated logs/metrics/traces and actionable owned alerts for technical/business failures | REQUIRED; thresholds pending D-006 |
| Recoverability | Encrypted/scoped backup and integrity-checked restore/rollback procedures | REQUIRED; RPO/RTO pending D-006 |
| Maintainability | Explicit modules, thin controllers, domain/application actions, ADR/contracts and automated fitness tests | REQUIRED |
| AI isolation | V1 works without AI; later AI capacity cannot starve commerce | REQUIRED across V1/V2 |

## 8. Acceptance criteria

These are product-level criteria. Detailed scenario values and state transitions come from approved Step 03 rules/contracts.

| AC ID | Acceptance criterion | Dependency |
| --- | --- | --- |
| AC-001 | Guest can browse/search/filter indexable product content rendered server-side without an account | Approved sitemap/SEO rules |
| AC-002 | Guest cart persists and merges deterministically after login without duplicating items or trusting stale final price | Cart merge/pricing rules |
| AC-003 | Guest can submit and securely revisit a quotation without a guessable identifier; abuse controls and authorization apply | Guest quote/access policy |
| AC-004 | Customer can complete an approved checkout flow; double/concurrent submit creates at most one order/business effect | D-001, D-002, D-004 and contracts |
| AC-005 | Parallel stock attempts cannot oversell and duplicate release/commit does not change stock twice | D-002 and schema/lock plan |
| AC-006 | Invalid/duplicate/out-of-order payment callbacks cannot create duplicate financial effects | D-004/D-007 and provider contract |
| AC-007 | Quotation revisions preserve history; accepted revision is immutable; concurrent conversion creates exactly one order | Approved quotation lifecycle/rules |
| AC-008 | Sales can operate only assigned/authorized CRM, quote and order scope; direct unauthorized HTTP access fails | D-003/D-005 |
| AC-009 | Admin capabilities follow explicit permissions; role labels do not bypass policy | D-005 |
| AC-010 | Carrier/search/Merchant/notification outage cannot corrupt or redefine commerce truth | D-007 and adapter contracts |
| AC-011 | Product price/availability in Merchant/search projections reconciles to authoritative sources; duplicate analytics events are prevented | Event/integration contracts |
| AC-012 | Critical public pages pass rendered metadata/canonical/indexation/schema and accessibility checks | Page inventory and D-006 |
| AC-013 | Critical flows pass unit/feature/permission/concurrency/integration/E2E suites and approved performance/security gates | Steps 49–51 |
| AC-014 | Monitoring/alerts, backup restore and deployment rollback are exercised before release | D-006/D-007 and Steps 52–54 |
| AC-015 | V1 catalog/cart/manual quote/checkout/order/payment/inventory passes when AI is disabled/unreachable | AI isolation architecture test |
| AC-016 | Production launch occurs only when item-level Global DoD evidence passes | Global DoD |

## 9. Decision register / REPORT GAP

| Decision | Required approval | Impact if unresolved |
| --- | --- | --- |
| [D-001 Pricing precedence/stacking/rounding](./decisions/D-001-pricing-policy.md) | APPROVED by Product Owner on 2026-08-23 | Pricing rules may be reconciled; D-003 still gates override authority/limits |
| [D-002 Inventory reserve/release/commit/expiry/backorder](./decisions/D-002-inventory-reservation-policy.md) | APPROVED by Product Owner on 2026-08-23 | Lifecycle and expiry policy approved; concrete method/TTL configuration remains D-004 |
| [D-003 Discount and quotation approval authority/limits](./decisions/D-003-discount-quotation-approval-policy.md) | APPROVED by Product Owner on 2026-08-23 | Balanced thresholds approved; VND and quotation-total basis must be confirmed by D-004 |
| D-004 VAT/invoice/payment/shipping/refund scope | Product + Finance + Operations | Blocks checkout/order/payment/shipping contracts |
| D-005 Identity/ownership/roles/permissions | Product + Security | Blocks auth/RBAC/CRM/resource access |
| D-006 Numeric NFR/SLO/RTO/RPO/retention/accessibility/residency | Product + Architecture + Operations/Security | Blocks final NFR validation and production topology |
| D-007 Environment/provider/contracts | Architecture + Security/Operations + business owner | Blocks integrations/foundation/production runbooks |
| D-008 AI use cases/data/tools/eval/cost | Product + AI Platform + Security/Privacy | Does not block V1; blocks all V2 AI work |

No dependent rule/schema/code may select a default for decisions that remain open. D-001–D-003 do not approve payment methods/TTL values, tax/shipping total composition, D-004, schema, API or event contracts.

## 10. Approval record

| Scope | Decision | Approver/authority | Date | Approved revision/evidence |
| --- | --- | --- | --- | --- |
| V1 PRD | APPROVED | Product Owner (user) | 2026-08-23 | Conversation approval: “Tôi phê duyệt Product Requirements v0.1…” |
| V1/V2 scope matrix | APPROVED | Product Owner (user) | 2026-08-23 | Conversation approval: “…V1/V2 Scope Matrix…” |
| V1 single-tenant decision | APPROVED | Product Owner (user) | 2026-08-23 | Conversation approval: “…V1 single-tenant; AI Platform thuộc V2.” |

## 11. Step 02 Definition of Done

| Check | Status | Evidence |
| --- | --- | --- |
| Personas and jobs-to-be-done documented | PASS | Section 4 |
| B2C/B2B/V1 operational use cases documented | PASS | Sections 5 and 8 |
| V1/V2/out-of-scope explicit | PASS | [Scope Matrix](./scope-matrix.md) |
| Acceptance criteria documented | PASS | Section 8 |
| Single-tenant V1 decision explicit | PASS | Section 3 and approval record |
| Unresolved business/NFR decisions not invented | PASS | Section 9 |
| Product Owner approval | PASS | Approval recorded on 2026-08-23 |

Step 02 is `DONE`. D-001–D-003 are approved separately. Step 03 remains gated by D-004–D-005 and final review of the complete rule matrix.

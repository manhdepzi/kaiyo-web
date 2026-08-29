# Kaiyo Web — Execution Master Plan

## 1. Document control

- Status: `ACTIVE PLAN — IMPLEMENTATION GATED`
- Created: 2026-08-23 (Asia/Bangkok)
- Last updated: 2026-08-29 — Steps 00–28 completed; Step 29 Product gallery/deep zoom and CRM Contact slices pass with browser evidence pending; Steps 30–35 have gated slices; Steps 48–54 controls active
- Scope: master execution plan for Promptbook steps `00–55`
- Delivery model: dependency-corrected execution while retaining original Step IDs
- V1: Commerce, B2B, CRM, CMS, SEO, Merchant/Analytics and production operations
- V2: AI Platform steps `36–47`, started only after the V1 commerce core passes its production gate
- Estimation: no sprint/date estimate until team size, velocity, traffic model and approved NFR targets exist
- Change boundary: this plan does not approve or create database schema, public API/event contracts, permissions, business rules, provider choices or destructive workflows

## 2. Sources of truth

Apply the precedence defined in [AI Engineering Governance](./docs/governance/engineering-governance.md):

1. Approved Business Rules / Product Requirements
2. ADRs
3. Database / API / Event contracts
4. Architecture Specification
5. Existing production code
6. Current task prompt

### Primary inputs

| Source | Role | SHA-256 / status |
| --- | --- | --- |
| [Architecture & Functional Specification v1.1](./Kaiyo-web/Website_Ecommerce_B2B_SEO_Architecture_Functional_Specification_v1.1_Production_Optimized.docx) | Product/architecture baseline; includes production optimization review | `8BD509E0DF451ECE8787E1EE2015494EB52AF20CF80AC8EEF451C31C51E7996C` |
| [AI Enterprise Full-Stack Engineering Promptbook v2.0](./Kaiyo-web/AI_Enterprise_FullStack_Engineering_Promptbook_v2.0.docx) | Required work-item sequence, artifacts and DoD | `9D2097D75B064EB9CE3623FD06C930AAD676E200440DA8120EB32411E448DCB2` |

### Existing repository artifacts

| Artifact | Current interpretation |
| --- | --- |
| [Engineering governance](./docs/governance/engineering-governance.md) | Step 00 revalidated against Promptbook v2.0 on 2026-08-23 |
| [AI task template](./docs/governance/ai-task-template.md) | Step 00 revalidated with artifact dependency/approval evidence fields |
| [Repository audit](./docs/audits/repository-audit.md) | Step 01 updated on 2026-08-23 with DOCX/GitHub/current repository evidence |
| [Risk register](./docs/audits/risk-register.md) | Step 01 updated; open/closed risks reflect the current baseline |
| [Dependency roadmap](./docs/audits/dependency-roadmap.md) | Gate A design lock and Steps 11–28 passed; Steps 29–30 await named sub-gates while Step 31 proceeds |
| [Product Requirements](./docs/product/product-requirements.md) | Version 0.1 approved by Product Owner on 2026-08-23 |
| [V1/V2 Scope Matrix](./docs/product/scope-matrix.md) | Version 0.1, V1 single-tenancy and AI-as-V2 boundary approved on 2026-08-23 |
| [D-004–D-007 Approval Pack](./docs/product/decision-approval-pack-D004-D007.md) | Proposed consolidated choices/inputs; not approved and does not open implementation gates |
| [Business Rules Matrix](./docs/business/business-rules-matrix.md) | 47 rules inventoried; D-001–D-003 and recommended Step 03 policy overlays approved; D-004/D-005 and conditional inputs remain gated |
| [Step 03 Revalidation Report](./docs/business/step-03-revalidation-report.md) | Lifecycle and GAP-03-06–13 selections recorded; dependent gates and final approval remain open |
| [Step 03 Approval Questionnaire](./docs/business/step-03-approval-questionnaire.md) | Recommended bundle approved by Product Owner selection `1` on 2026-08-23; D-004/D-005/D-006 dependencies remain open |
| [System architecture](./docs/architecture/system-architecture.md) | Approved V1 provider-neutral baseline; concrete contracts/provider bindings remain downstream/production gates |
| [Domain boundaries](./docs/architecture/domain-boundaries.md) | Step 05 approved ownership, application ports, forbidden coupling and acyclic dependency graph |
| [V1 ERD and entity catalog](./docs/architecture/v1-erd.md) | Step 06 approved logical aggregates/cardinalities; physical V1 migrations now exist through `000018` |
| [Schema dictionary](./docs/database/schema-dictionary.md) | Step 07 approved MySQL 8.4 table/type/FK/unique/check/state/retention design; migrations `000001`–`000018` applied to local `kaiyo` |
| [Index and concurrency plan](./docs/database/index-concurrency-plan.md) | Step 07 approved critical access paths, indexes, lock order and durable idempotency design |
| [API conventions](./docs/api/api-conventions.md) | Step 08 approved `/api/v1`, DTO/error/idempotency/pagination/auth/webhook/versioning conventions |
| [OpenAPI skeleton](./docs/api/openapi.yaml) | OpenAPI 3.1 skeleton passes Redocly 2.47.0 spec and recommended lint |
| [ADR registry](./docs/adr/README.md) | Step 09 contains template, nine accepted V1 ADRs and one explicitly proposed V2 AI ADR |
| [Coding standards](./docs/governance/coding-standards.md) | Step 10 approved module/layer/naming/transaction/query/security/test/migration implementation standard |
| [Testing strategy](./docs/testing/testing-strategy.md) | Step 49 bootstrapped before implementation; executable suites and release evidence remain open |
| [Performance budgets](./docs/performance/performance-budgets.md) | Step 50 budgets bootstrapped from D-006; measurements/load evidence remain open |
| [Security threat model](./docs/security/threat-model.md) | Step 51 bootstrapped; continuous review and executable security evidence remain open |
| [Observability and SLO plan](./docs/operations/observability-slo.md) | Step 52 signals/SLOs bootstrapped; instrumentation and alert exercises remain open |
| [CI/CD design](./docs/operations/ci-cd.md) | Step 53 pipeline controls bootstrapped; reproducible deploy/rollback evidence remains open |
| [Disaster recovery plan](./docs/operations/disaster-recovery.md) | Step 54 recovery controls bootstrapped; provider binding and timed restore drill remain open |
| [Step 11 task/evidence](./docs/tasks/step-11-laravel-foundation.md) | Laravel 13 foundation passes local and official PHP 8.5.9 tests/static/style/build/security/contract checks |

The [Global Definition of Done](./docs/governance/engineering-governance.md#10-global-definition-of-done) is the release gate. This plan references it and does not replace it with a weaker checklist.

## 3. Status legend

| Status | Meaning |
| --- | --- |
| `DONE` | Artifact and applicable DoD have evidence and approval |
| `REVALIDATE` | Artifact exists but must be checked against newer/higher-priority sources |
| `BLOCKED` | Required decision or upstream artifact is missing |
| `READY` | Dependencies and approvals are satisfied; implementation may start |
| `IN PROGRESS` | Work is active and not yet through its gate |
| `PENDING` | Work has not started or is waiting behind the dependency chain |
| `N/A` | Not applicable, with a recorded reason and approver where required |

No step may move to `DONE` because a document/file merely exists. It needs artifact review, required approval and verification evidence.

## 4. Execution policy and waves

The Promptbook IDs remain stable for traceability, but technical dependencies take priority over strict numeric execution.

1. **Wave A — Re-baseline and design lock:** `00–10`, with early bootstrap of controls from `48–54`.
2. **Wave B — V1 foundation and domain core:** `11–19`.
3. **Wave C — V1 Commerce/B2B:** `20–26`.
4. **Wave D — V1 frontend, content and growth:** `27–35`.
5. **Wave E — V1 production readiness:** formally close `48–55` and run Global DoD.
6. **Wave F — AI Platform V2:** `36–47`, followed by an AI-aware rerun of `48–55` before AI production enablement.

Cross-cutting controls are bootstrap-early, close-late:

- `48 Event Catalog`: establish conventions with Steps 08–09; update with every producer/consumer; close before release.
- `49 Testing Strategy`: define after Steps 02–10; implement tests with every feature; close on release evidence.
- `50 Performance`: define budgets in Steps 02/04; profile continuously; close with realistic load tests.
- `51 Security`: threat-model after architecture/contracts; test continuously; close with no unresolved Critical/High issue.
- `52 Observability`: define signals/SLO inputs before Step 11; instrument each module; close with actionable alerts.
- `53 CI/CD`: bootstrap in Step 11; extend gates with each layer; close with reproducible deploy/rollback.
- `54 Disaster Recovery`: design before persistent schema/storage; implement with infrastructure; close only after restore drill.

## 5. Step register `00–55`

| ID | Release / layer | Function | Current status | Dependencies and approval gate | Required action | Required artifact | Test / verification | Step DoD |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 00 | V1 / Governance | AI Engineering Governance | `DONE` | Promptbook v2.0; no product rule required | Revalidated governance/template with master prompt and added approval/dependency/AI-outage controls | Updated governance and AI task template | Required-control trace, 16-item Global DoD count and template-field checks passed on 2026-08-23 | Agent has no right to invent; Global DoD referenced |
| 01 | V1 / Layer 1 | Repository Audit | `DONE` | Step 00 | Re-audited after DOCX/GitHub baseline; corrected obsolete claims; refreshed mismatches, risks and dependency roadmap; no feature changes | Repository audit, risk register, dependency roadmap | Current inventory and spec comparison completed; known gaps/mismatches recorded | Every known mismatch recorded; no feature code |
| 02 | V1 / Layer 1 | Product Requirements and Scope | `DONE` | Steps 00–01 passed; Product Owner approval recorded | Product Requirements v0.1, V1/V2 scope, V1 single-tenancy and AI-as-V2 boundary approved; business/architecture decisions remain separate gates | Approved PRD and scope matrix | Requirement/scope/acceptance trace and approval record verified | V1/V2 explicit; single-tenant approved; AI deferred to V2 |
| 03 | V1 / Layer 1 | Business Rules Matrix | `DONE` | Step 02; D-001–D-005 and complete V1 policy matrix approved under delegated authority | Approved 47-rule V1 policy matrix; AI writes explicitly V2-deferred; missing provider/legal inputs fail closed | Approved Business Rules Matrix and decision records | 47 unique rules, critical walkthrough, lifecycle/dependency review and approval trace verified | Critical V1 rules unambiguous and approved |
| 04 | V1 / Layer 2 | System Architecture | `DONE` | Steps 02–03; D-006 A2 engineering profile; D-007 provider-neutral baseline; delegated Architecture approval | Approved Modular Monolith, runtime/data/adapters and measurable NFR mapping; commerce remains independent from AI | Approved diagrams and NFR mapping | Dependency/failure-mode review and AI-disabled architecture assertion verified | Commerce core has no AI dependency |
| 05 | V1 / Layer 2 | Domain Boundaries | `DONE` | Steps 03–04 approved | Approved ownership, application-port responsibilities, conceptual facts and forbidden coupling for all V1 domains plus V2 AI boundary | [Domain boundary map](./docs/architecture/domain-boundaries.md) | Required-boundary coverage and dependency graph cycle check passed (35 edges, 17 nodes, no cycle) | No circular ownership |
| 06 | V1 / Layer 2 | ERD | `DONE` | Steps 03–05 approved | Approved six-part Mermaid logical ERD/entity catalog covering identity/CRM, catalog/pricing, warehouse inventory, quote/order, payment/tax/shipping and content/projections; V2 excluded | [V1 ERD and entity catalog](./docs/architecture/v1-erd.md) | Required entity coverage, rule trace, ownership/cardinality, god-table and premature-V2 reviews passed | No god table; V2 not implemented early |
| 07 | V1 / Layer 3 design | Database and Index Plan | `DONE` | Steps 03, 05–06 approved; D-006 technical retention with unresolved legal deletion fail-closed | Approved MySQL 8.4 schema dictionary plus index/query/concurrency/idempotency plan; no migrations created | [Schema dictionary](./docs/database/schema-dictionary.md) and [index/concurrency plan](./docs/database/index-concurrency-plan.md) | 22 required-table families found, money/state/soft-delete/lock/idempotency/EXPLAIN controls verified; zero migration files | Critical access paths mapped; constraints protect integrity by design |
| 08 | V1 / Layer 2 | API Contract | `DONE` | Steps 03, 05–07 approved; provider-specific payloads explicitly disabled until binding | Approved `/api/v1`, DTO/Problem Details, cursor/filter/sort, session/CSRF, idempotency, rate limit, versioning and webhook requirements plus OpenAPI skeleton | [API conventions](./docs/api/api-conventions.md) and [OpenAPI](./docs/api/openapi.yaml) | Redocly 2.47.0 `spec` and `recommended` lint pass; examples/review confirm opaque IDs and no ORM/provider leakage | Error and idempotency contract standardized |
| 09 | V1 / Governance | ADR Registry | `DONE` | Steps 02–08 and Architecture baseline approved | Added ADR template/registry; accepted Modular Monolith, SSR stack, runtime/MySQL isolation, Redis/outbox, search adapter, object storage, API versioning, single tenancy and managed provider-neutral topology; AI abstraction remains proposed V2 | [ADR registry](./docs/adr/README.md) and ten ADR files | 10 registry rows/files, 9 accepted V1 decisions, AI ADR non-authoritative and links verified | AI/implementers must read applicable ADRs before architecture change |
| 10 | V1 / Governance | Coding Standards | `DONE` | Steps 05, 07–09 approved | Approved `App\Modules` layering/dependency/naming and Action/DTO/Query/Event/Job/exception/migration/test standards; thin delivery, Eloquent isolation, transaction/idempotency, no Blade business logic | [Coding standards](./docs/governance/coding-standards.md) | Required policy categories, approved/forbidden examples and consistency review passed | Two implementers can produce consistent code |
| 11 | V1 / Layer 3 | Laravel Foundation | `DONE` | Steps 00–10 and early `49`–`54` controls passed; PHP 8.5 target verified | Laravel 13/Livewire foundation, environment-driven services, stateless health/readiness, correlation logging, explicit proxy/cookie security, tests/static/style/build and CI baseline implemented | [Step 11 task/evidence](./docs/tasks/step-11-laravel-foundation.md), [observability evidence](./docs/operations/observability-slo.md) | Current health 6/17 and full 182/1268; local MySQL/Redis readiness and HTTP smoke, Pint, Larastan 8, build/audits/OpenAPI/cache checks pass | Fresh boot, dependency readiness, tests and build pass |
| 12 | V1 / Layer 3 | Authentication | `DONE` | Steps 02–03, 07–11 and D-005 passed | Fortify-backed registration/login/verification/reset, encrypted TOTP/recovery, hashed session registry/revocation, disabled-account termination, throttling and security audit implemented; permission redirects remain Step 13 | [Step 12 task/evidence](./docs/tasks/step-12-authentication.md), Identity module, migration and security tests | 16 tests pass; MySQL 8.4 migration/rollback, disabled/cross-account/session/throttle/2FA recovery cases, Pint/PHPStan 8/build pass | Disabled and staff-2FA protections pass; no role/permission invented |
| 13 | V1 / Layer 3 | RBAC | `DONE` | Steps 03, 05, 07, 09–12, D-003 and D-005 passed | Implemented 54-code permission catalog, configurable role bundles, direct/role scoped grants, database-authoritative checks, dual-control delegation, instant versioned revocation, staff classifier and exact 60-minute reviewed break-glass | [Step 13 task/evidence](./docs/tasks/step-13-rbac.md), [permission matrix](./docs/security/permission-matrix.md), authorization migration/actions/tests | Full 26 tests/289 assertions; MySQL authorization 10/211; direct/cross-scope/stale/revoked/role-name/dual-control/break-glass cases; PHPStan 8/Pint/build pass | Server permission+scope is truth; no admin/role/UI bypass |
| 14 | V1 / Layer 3 | CRM Core | `DONE` | Steps 03, 05–07, 10–13 and CRM-D1/D-005 passed | Implemented Customer, Company, membership/capability, Contact, Lead, ownership history, privacy-safe exact identity, review-only fuzzy matching and locked idempotent conversion | [Step 14 task/evidence](./docs/tasks/step-14-crm-core.md), CRM migration/actions/tests | Full SQLite 34 tests/325 assertions; MySQL 8.4 CRM 8/30; exact/fuzzy/scope/version/membership/ownership/conversion/rollback; PHPStan 8/Pint/build pass | Exact duplicates and conflicting conversion blocked; retry returns one outcome |
| 15 | V1 / Layer 3 | Catalog | `DONE` | Steps 03, 05–07, 10–14 passed | Implemented Category hierarchy, Brand, transactional Product+Variant, reserved SKU/slug, typed attributes, staged media references, status/version guards, flat redirects and mutation facts | [Step 15 task/evidence](./docs/tasks/step-15-catalog.md), Catalog migration/actions/query/tests | Full SQLite 40 tests/361 assertions; MySQL 8.4 Catalog 6/36; hierarchy/atomicity/reservation/status/redirect/typed-value/query-count; PHPStan 8/Pint/build pass | SKU/slug remain reserved; cycles and N+1 path blocked |
| 16 | V1 / Layer 3 | Pricing | `DONE` | Steps 03, 05–07, 10, 13–15 and D-001/D-003/D-004 VND basis passed | Implemented deterministic integer PricingEngine, database eligibility resolver, five-layer replacement, non-stacking promotion calculation, dual-control revisions and immutable snapshots | [Step 16 task/evidence](./docs/tasks/step-16-pricing.md), Pricing migration/domain/actions/resolver/tests | Full SQLite 51 tests/389 assertions; MySQL 8.4 Pricing 11/28; golden/ambiguity/HALF_UP/auth/SoD/resolver/snapshot/rollback; PHPStan 8/Pint/build pass | Approved precedence and rounding fully tested; snapshots immutable |
| 17 | V1 / Layer 3 | Inventory | `DONE` | Steps 03, 05–07, 10, 13, 15–16 and D-002/D-004 passed | Implemented constrained warehouse balances, immutable movements, dual-control adjustments and locked idempotent reserve/release/commit/payment-expiry lifecycle | [Step 17 task/evidence](./docs/tasks/step-17-inventory.md) and Inventory domain/concurrency suite | 57 tests/416 assertions; MySQL 8.4 isolated migration/6 tests/27 assertions, real two-process 7-of-10 no-oversell probe, immutable trigger and rollback checks passed | Available stock never negative |
| 18 | V1 / Layer 3 | Search | `DONE` | Steps 04–05, 07, 09–11, 15 and ADR-0005 passed | Implemented provider-neutral SearchService/Adapter with DB ranking, exact-SKU priority, approved filters, bounded stable pagination and versioned Catalog-fact cache invalidation | [Step 18 task/evidence](./docs/tasks/step-18-search.md) and adapter contract tests | Full 62 tests/430 assertions; Search 5 tests/14 assertions passed on SQLite and isolated MySQL 8.4; PHPStan/Pint/build pass | No search-engine coupling |
| 19 | V1 / Layer 3 | Media | `DONE` | Steps 04–11, 13, 15 and D-007 passed | Implemented storage-neutral quarantine-first upload, detected-content validation, scanner port, WebP variants, Catalog usage FK, governed temporary private access and idempotent orphan cleanup | [Step 19 task/evidence](./docs/tasks/step-19-media.md) and Media security suite | Full 67 tests/456 assertions; MySQL critical 16/61, five Media CHECKs, FK/permissions and rollback pass; PHPStan/Pint pass | Executable/MIME mismatch rejected |
| 20 | V1 / Layer 3 | Cart | `DONE` | Steps 03, 07, 12–18 and D-001/D-002 passed | Implemented HMAC guest identity, single active owner Cart, unique/versioned/idempotent lines, deterministic login merge and explicitly advisory Pricing/Inventory preview | [Step 20 task/evidence](./docs/tasks/step-20-cart.md) and Cart concurrency suite | Cart 4 tests/16 assertions; MySQL combined 20/77, five CHECKs/two active-owner indexes, real two-process merge and rollback pass | Cart merge deterministic |
| 21 | V1 / Layer 3 | Checkout | `DONE` | Steps 03, 07–08, 12–20 and provider-neutral Step 23–24 bindings passed | Implemented Customer-only idempotent Checkout with authoritative repricing, deterministic stock allocation/reservation, immutable Order/address/line snapshots and fail-closed Tax/Payment/Shipping ports | [Step 21 task/evidence](./docs/tasks/step-21-checkout.md), Checkout migration/action/contracts/tests | Core SQLite 75/496 and MySQL 25/103; Step 24 integration asserts one Payment/Attempt and one complete Shipment per accepted Order | Double submit safe; provider-neutral Payment/Shipping E2E closed; named providers remain contract-gated |
| 22 | V1 / Layer 3 | Order | `DONE` | Steps 03, 05–08, 13, 16–17, 20–21 passed | Implemented exact evidence-bound ORD-L1 forward state machine, scoped CANCEL-D1 request/decision, immutable audit, dispatch Inventory commit, atomic cancellation release/Payment preparation and versioned state-change facts | [Step 22 task/evidence](./docs/tasks/step-22-order.md), lifecycle migration/actions/tests | Current Order 4/30 with idempotent Notification consumption; prior combined Order/Payment/Shipping/Checkout/Quotation 24/181 with four MySQL-only skips; atomic fact rollback passes | Illegal/stale transitions blocked; cancellation boundary, compensation and outbox atomicity pass |
| 23 | V1 / Layer 3 | Payment | `DONE — PROVIDER-NEUTRAL CORE` | Steps 03, 05–09, 13, 16, 21–22 and D-004/D-007 passed; named online gateway remains disabled | Implemented separate Payment/Attempt/Transaction/Event/Refund/Reconciliation truth, Checkout registration, transactional verified-Payment fact, expiry-safe Inventory protection, reliable Order confirmation, authenticated adapter callbacks and dual-control full refund | [Step 23 task/evidence](./docs/tasks/step-23-payment.md), Payment migration/domain/adapters/tests | Current Payment 4/38 plus one MySQL-only skip and full 182/1268; Payment/Inventory/Outbox 17/110 plus one skip; PHPStan 8 zero errors; prior MySQL critical 32/150, nine CHECKs/eight triggers and rollback/re-migrate passed | Duplicate/conflicting/out-of-order webhook and refund effects are safe; unknown and late-terminal results reconcile |
| 24 | V1 / Layer 3 | Shipping | `DONE — PROVIDER-NEUTRAL CORE` | Steps 03, 05–09, 13, 17, 21–23 and D-004/D-007 baseline passed; named carrier remains disabled | Implemented configured/manual single-Shipment truth, complete item lineage, evidence-bound warehouse lifecycle, carrier adapter isolation, authenticated monotonic tracking, reconciliation and versioned state-change facts | [Step 24 task/evidence](./docs/tasks/step-24-shipping.md), Shipping migration/actions/adapters/tests | Current Shipping 4/33 plus one MySQL-only skip; warehouse, booking unknown, tracking, correction and delivery facts pass; PHPStan/Pint clean | Carrier outage is visible and does not block commerce core; duplicate/out-of-order effects are safe |
| 25 | V1 / Layer 3 | Quotation | `DONE — DOMAIN CORE` | Steps 03, 05–08, 12–19 and approved D-001–D-005 passed | Implemented opaque guest/authenticated-Customer creation, anti-abuse, deterministic snapshots, exact approval tiers/SoD, immutable recalculated revisions, guarded lifecycle and versioned state-change facts | [Step 25 task/evidence](./docs/tasks/step-25-quotation.md), Quotation migration/domain/actions/tests | Current focused Quotation 8/66 plus one MySQL-only skip; repeated view emits no false state fact and conversion failure rolls back its fact | Accepted/source revisions remain immutable; approvals do not carry; exact lifecycle enforced |
| 26 | V1 / Layer 3 | Quote to Order | `DONE — TRANSACTIONAL CORE` | Steps 17, 21–25 and approved conversion/reservation rule passed | Implemented permission-gated one-time Accepted revision conversion with exact snapshots, deterministic stock allocation/reservation, Payment/Shipping registration, durable idempotency, versioned conversion fact and full rollback | [Step 26 task/evidence](./docs/tasks/step-26-quote-to-order.md), conversion migration/action/tests | Current focused Quotation 8/66 plus one MySQL-only skip; source XOR/unique constraints and conversion fact rollback pass | Exactly one Order per Accepted revision; stock failure leaves no partial effect |
| 27 | V1 / Layer 4 | Frontend Architecture | `DONE — ARCHITECTURE CONTRACT` | Steps 02–10 passed; Steps 11–26 provide executable domain contracts | Defined component taxonomy, single writable-state ownership, Blade SSR/Livewire/Alpine boundaries, asset strategy and complete cross-surface UX state taxonomy | [Frontend architecture](./docs/frontend/frontend-architecture.md), [state matrix](./docs/frontend/frontend-state-matrix.md) and [task evidence](./docs/tasks/step-27-frontend-architecture.md) | Architecture fitness test and full suite 103/663 passed; production asset build, PHPStan level 8, Pint and HTTP probe passed | SSR ownership clear; states are not mixed |
| 28 | V1 / Layer 4 | Design System | `DONE — EXECUTABLE PRIMITIVES` | Steps 02 and 27 passed; WCAG 2.2 AA target approved under D-006 | Implemented semantic light/dark tokens, typography/grid/spacing/motion rules and accessible Button/Input/Alert/Badge/Card/Empty/Skeleton primitives; migrated authentication UI | [Design system](./docs/frontend/design-system.md), [task evidence](./docs/tasks/step-28-design-system.md), CSS/components/tests | Nine contrast pairs, focus/reduced-motion/token discipline and real auth SSR pass; full suite 116/701; production build, PHPStan 8 and Pint pass | Components use semantic tokens; no raw palette classes |
| 29 | V1 / Layer 4 | Public Website | `IN PROGRESS — BROWSER GATE` | Steps 15–21, 25 and 27–28 passed; Step 33 Page integration available | Public shell/Catalog, secure Cart, Checkout/receipt, Quotation, CMS Page, CRM Contact capture and Product gallery/deep zoom are implemented through domain actions/DTOs | [Step 29 task/evidence](./docs/tasks/step-29-public-website.md), public controllers/views/DTO readers/tests | Full regression 192/1,433 with four environment skips; active-only/SSR/idempotency/isolation checks, build, PHPStan 8 and Pint pass | Functional flows pass; final browser-assisted responsive/accessibility/deep-zoom evidence remains |
| 30 | V1 / Layer 4 | Customer Portal | `IN PROGRESS — MISSING-DOMAIN GATES` | Steps 12–14, 19, 22–26, 27–28 passed | Profile, dashboard, own Order detail, Quotation lifecycle, Security and Customer-scoped in-app Order notifications implemented; Address/Wishlist/Review/preferences/outbound-channel contracts and cancellation entitlement remain open | [Step 30 task/evidence](./docs/tasks/step-30-customer-portal.md), portal/security/notification actions, DTO queries, views and tests | Account 5/40; combined Order/Account notification slice 9/75; ownership isolation, replay dedupe, mark-read idempotency, session revocation and private responses pass | Partial; missing-domain and final browser/query gates remain |
| 31 | V1 / Layer 4 | Sales CRM UI | `IN PROGRESS — CONTRACT/NFR GATES` | Steps 13–14, 22, 25–28 passed | Customer/Lead/Company directories and detail flows plus entitlement-separated Quote/Order reads implemented; Tasks/Performance/Saved Search wait on owning definitions/contracts | [Step 31 task/evidence](./docs/tasks/step-31-sales-crm-ui.md), Sales controllers/read DTOs/views/tests | 2FA, private SSR, module/resource permissions, cursor/filter, stale update, idempotent conversion/membership and entitlement separation pass | Partial; missing contract and large-data/browser gates remain |
| 32 | V1 / Layer 4 | Admin UI | `IN PROGRESS — AUDIT SLICE` | Steps 13–19, 22–28 passed; KPI definitions remain a sub-gate | Private Admin shell and high-permission authorization audit directory implemented; KPI dashboards/import-export/settings wait on approved definitions and owning modules | [Step 32 task/evidence](./docs/tasks/step-32-admin-ui.md), Admin audit DTO/query/controller/view/test | Direct denial, staff 2FA, private headers, cursor/filter and snapshot-hash non-disclosure pass | Partial; KPI/module and final query/browser gates remain |
| 33 | V1 / Layer 3 | CMS | `IN PROGRESS — MYSQL VERIFIED; BROWSER GATE` | Steps 03, 05–07, 10–13, 19, 27–28 passed; isolated MySQL gate passed; browser evidence pending | Type-specific roots/revisions, immutable lineage, replacement/live preservation, unpublish, Page/Article/FAQ/Banner schedules, governed media references/orphan protection, private Admin workspace, sanitized public projections, safe CTA and Email Template controls are implemented; finish browser evidence | [Step 33 task/evidence](./docs/tasks/step-33-cms.md), CMS migrations/actions/queries/controllers/views/tests | Expanded MySQL 8.4 matrix 69/475 with one committed-probe skip includes all 15 CMS cases; default full 182/1268; permission/2FA/private-header/optimistic/idempotent/stale-schedule/media/query-bound/sanitization/injection, PHPStan 8 and Pint pass | Domain/Admin/public lifecycle, media and MySQL pass; browser gate remains |
| 34 | V1 / Layer 4 | Technical SEO | `IN PROGRESS — LANDING/BROWSER/PRODUCTION GATES` | Steps 02–04, 15, 27–29 and available Step 33 public contracts passed | Explicit canonical/robots, public-sellable sitemap, stable pagination, approved Product schema inventory/composer, transactional noindex and direct-to-current active Catalog redirects implemented; finish approved landing pages and browser/production-host evidence | [Step 34 task/evidence](./docs/tasks/step-34-technical-seo.md), [schema inventory](./docs/seo/structured-data-inventory.md), SEO controllers/layout/redirect resolver/tests | Technical SEO 5/51 and full regression 182/1268; exact decoded schema payload, canonical/indexation/sitemap/robots/direct-redirect checks, PHPStan 8, Pint and build pass | Structured-data slice passes; landing/browser/production gates remain |
| 35 | V1 / Layer 3/4 | Merchant and Analytics | `IN PROGRESS — PROVIDER/ATTRIBUTION/INTEGRATION GATES` | Provider-neutral scope allowed by approved domain contracts; named providers remain blocked by D-007 | Durable Merchant batches rebuild authoritative Catalog/Pricing/Inventory facts; Analytics enforces exact allow-list, consent/PII/dedupe and private-monitor controls; Order lifecycle, Catalog, Inventory and Payment producers use the transactional outbox; finish attribution, remaining producers, concrete providers and staging/load/privacy evidence | [Step 35 task/evidence](./docs/tasks/step-35-merchant-analytics.md), [Event Catalog](./docs/events/event-catalog.md), Growth/Foundation contracts/actions/relay/Admin/tests | Merchant/Analytics focused 8/51; current Order integration regression 24/181 with four MySQL-only skips; partial retry, provider-disabled safety, consent suppression, PII rejection, event dedupe and private 2FA monitor pass | Provider-neutral slice passes; no duplicate delivery identity; external/provider and attribution gates remain |
| 36 | V2 / Layer 5 | AI Platform Foundation | `PENDING` | V1 Global DoD and controlled launch complete; Decision `D-008`; approved AI ADR/data policy | Define independent AI bounded context, gateway/model/prompt/conversation/tool/RAG/agent/usage/eval/audit interfaces and global feature flag; no live provider without approved config | AI architecture, interfaces and feature flags | AI-disabled commerce E2E; boundary/dependency tests | Commerce works with AI disabled |
| 37 | V2 / Layer 5 | LLM Gateway and Model Registry | `PENDING` | Step 36; approved providers/models/fallback/cost policy | Implement provider-neutral gateway, registry, timeout/retry/fallback, streaming and structured-output validation; no model IDs in business code | Gateway, registry and provider contract tests | Adapter swap, timeout/fallback, schema rejection and config tests | Provider swap does not change agent business logic |
| 38 | V2 / Layer 5 | Prompt Management | `PENDING` | Steps 36–37; approved prompt lifecycle | Implement versioned Prompt Registry with immutable published revision, variables whitelist, publish/rollback, audit and evaluation status | Prompt registry and rollback workflow | Publish/rollback, hash/trace, variable validation and permission tests | Every AI response traceable to prompt version |
| 39 | V2 / Layer 5 | Conversation and Request Trace | `PENDING` | Steps 36–38; approved retention/redaction/access policy | Implement conversation/message/request metadata for provider/model/prompt/latency/tokens/cost/status/tools/citations; do not store chain-of-thought | Conversation domain and request trace | User/company isolation, retention/redaction and trace completeness tests | Cross-user/company isolation passes |
| 40 | V2 / Layer 5 | Knowledge Base and RAG | `PENDING` | Steps 19, 33–34, 36–39; approved sources/access scopes/vector ADR | Implement Source→Parse→Normalize→Chunk→Embed adapter pipeline and scoped retrieval/citations; propagate updates/deletes | Ingestion pipeline, retriever and citation model | Source/version/access, poisoning controls, citation and delete-propagation tests | Sources traceable; deleted content not retrieved |
| 41 | V2 / Layer 5 | Agent Runtime and Tool Registry | `PENDING` | Steps 36–40; approved tool contracts/policies | Implement intent/tool loop with external policy enforcement; registry includes schema, read/write impact, permission, idempotency, timeout and audit; no direct DB access | Agent runtime, tool registry and policy layer | Unauthorized/injected/invalid/duplicate tool-call tests | Unauthorized tool calls blocked |
| 42 | V2 / Layer 5 | AI Sales Assistant | `PENDING` | Steps 16–18, 36–41; approved use case/data scope | Implement needs discovery, grounded product comparison, approved price/availability reads, customer-context summary and follow-up draft; never override PricingEngine | Sales agent and evaluations | Grounding/citation, stale data, permission and price/stock hallucination tests | No invented price or stock |
| 43 | V2 / Layer 5 | AI Customer Support | `PENDING` | Steps 22, 25, 36–41; approved escalation/privacy policy | Implement grounded FAQ/policy/product/status assistance through authorized read tools and human escalation | Support agent and evaluations | Cross-user isolation, citation, low-confidence/high-risk escalation tests | Isolation and escalation pass |
| 44 | V2 / Layer 5 | AI Quotation Assistant | `PENDING` | Steps 16–17, 25, 36–43; approved write/high-impact policy | Build requirement extraction through exact immutable draft command, policy and human confirmation; approved tool may create draft only; cannot accept, bypass discount, capture or create final order | Quotation agent and human approval flow | Proposal hash, stale approval, authz, idempotency and forbidden-action tests | No autonomous high-impact commit |
| 45 | V2 / Layer 5 | AI Evaluation | `PENDING` | Steps 36–44; approved evaluation thresholds | Version datasets/runner for accuracy, groundedness, citations, hallucination, tool selection/arguments, refusal/escalation, latency, tokens/cost and safety | Eval dataset, runner and report | Golden-case reproducibility and model/prompt comparison | Critical AI regression blocks publish |
| 46 | V2 / Layer 5 | AI Cost and Reliability | `PENDING` | Steps 36–45; approved budgets/alerts/fallback | Track provider/model/tokens/cost/latency/cache/failure by actor/conversation/agent; add budget/rate controls and privacy-safe caching only where approved | Usage/cost telemetry and dashboard | Budget/rate, fallback/outage, cache isolation and cost reconciliation tests | No unbounded spend path |
| 47 | V2 / Layer 5 | AI Security and Guardrails | `PENDING` | Steps 36–46; approved AI threat model/risk acceptance | Threat-model prompt/indirect injection, leakage, exfiltration, tool abuse, escalation, RAG poisoning and malicious docs; enforce allow-lists/authz outside LLM/schema validation/audit/rate limits | AI threat model, guardrails and red-team suite | Injection, poisoned document, tool escalation and cross-user red-team tests | LLM cannot grant itself permission |
| 48 | V1 then V2 rerun / Layer 2 | Event Catalog | `IN PROGRESS — OUTBOX CONCURRENCY VERIFIED` | Steps 05, 07 and ADR-0004 passed; update with each producer/consumer | Versioned internal Order, Quotation, Shipment, Catalog, Inventory and Payment facts use the MySQL transactional outbox; released facts invalidate Search, confirm/reconcile Orders and create replay-safe in-app Order notifications; relay uses durable identity, SKIP LOCKED claim, lease recovery, bounded retry/dead-letter, private monitor and payload-safe CLI status gates; finish remaining facts/consumers/retention | [Event Catalog](./docs/events/event-catalog.md), migrations 000018/000020, Foundation/domain dispatch actions/commands/Admin/tests | Order/Account notification 9/75; Quotation 8/66 and Shipping 4/33 with one MySQL-only skip each; Inventory 7/39; prior MySQL two-worker probe 12 single-attempt facts; producer/consumer replay and rollback gates pass | Current producers, consumers, relay idempotency and MySQL concurrency pass; remaining catalog/consumer/retention gates stay open |
| 49 | V1 then V2 rerun / Layer 6 | Testing Strategy | `IN PROGRESS — BOOTSTRAPPED` | Strategy approved after Steps 02–10; suites implemented with every step | Maintain risk-based matrix for unit, feature, permission, concurrency, integration, E2E, SEO, API contracts and AI eval where applicable | [Testing strategy](./docs/testing/testing-strategy.md) and evolving executable suites | Critical stock race, double checkout, duplicate webhook, quote conversion and later AI cases | Critical paths automated |
| 50 | V1 then V2 rerun / Layer 6 | Performance Engineering | `IN PROGRESS — BOOTSTRAPPED` | Budgets approved from Steps 02/04; executable system/data profile still required | Profile before optimization; review DB EXPLAIN/N+1/indexes, cache invalidation, queue priority, CDN/assets and realistic load; isolate later AI workload | [Performance budgets](./docs/performance/performance-budgets.md) and later measurement report | Load tests with production-like flows/data; query/bundle/queue measurements | Commerce remains stable under approved load and later AI load |
| 51 | V1 then V2 rerun / Layer 6 | Application Security | `IN PROGRESS — BOOTSTRAPPED` | [Threat model](./docs/security/threat-model.md) approved after Steps 04–10; updated each module | Cover CSRF, XSS, authz, mass assignment, rate limits, session, webhook, upload, CSP, secrets, dependencies and PII; add AI controls on V2 rerun | Threat model, later security report and regression tests | SAST/SCA/config/DAST as applicable plus manual threat review | No unresolved Critical/High finding |
| 52 | V1 then V2 rerun / Layer 6 | Observability and SLO | `IN PROGRESS — LOCAL READINESS/OUTBOX EVIDENCE` | [Signals/SLO plan](./docs/operations/observability-slo.md) approved; instrumentation from Step 11 onward | Stateless `/ready` checks configured DB/cache and sanitizes failure; `outbox:status` provides payload-safe counts/ages and only enforces deployment-supplied gates; continue correlated metrics/logs/traces, dashboards, provider alerts and owned exercises | [Observability plan/evidence](./docs/operations/observability-slo.md), health/outbox commands and tests | Health 6/17, Outbox 7/45, full 182/1268, live local dependency HTTP 200, simulated HTTP 503, live scheduler, empty healthy outbox status and MySQL relay probe pass; PHPStan 8 zero errors | Local dependency/outbox probes actionable; provider dashboards/alerts and production exercises remain |
| 53 | V1 then V2 rerun / Layer 6 | CI/CD | `IN PROGRESS — LOCAL PIPELINE EQUIVALENT PASSED` | [CI/CD design](./docs/operations/ci-cd.md) approved; executable baseline begins Step 11 | GitHub quality pipeline runs SQLite quality plus MySQL 8.4 critical/CMS/Outbox suites and isolated two-worker probe before build/contracts/audits; finish remote run evidence, immutable artifact, staging approval/deploy/rollback | [Quality workflow](./.github/workflows/quality.yml) and [CI/CD runbook](./docs/operations/ci-cd.md) | Default 182/1268 plus four documented skips; MySQL 69/475 plus one committed-probe skip; two-worker 6/6 probe, Composer validation, Pint and PHPStan 8 pass locally; remote run/staging rollback remain | Application gates are reproducible locally; remote deploy evidence remains |
| 54 | V1 then V2 rerun / Layer 6 | Disaster Recovery | `IN PROGRESS — BOOTSTRAPPED` | [DR plan](./docs/operations/disaster-recovery.md) approved; provider/environment binding remains open | Back up DB, object storage and required configuration metadata with approved encryption/off-site/retention; provider outage and AI-disabled runbooks | DR plan and later restore report | Timed restore drill, integrity reconciliation and outage exercises | Restore tested against approved RPO/RTO |
| 55 | V1 launch then V2 controlled enablement / Layer 6 | Production Launch | `PENDING` | All release-scoped steps and Global DoD pass; approvals recorded | Verify DNS/TLS/CDN/WAF, secrets, data stores, queues, storage, mail, integrations, search, SEO, analytics, flags, monitoring and backups; controlled rollout; 24h/7d review; no V2 scope creep in V1 launch | Launch checklist and post-launch review | Smoke/E2E, rollback readiness, alert/backup evidence and Global DoD audit | Global DoD passes |

## 6. Decision register and STOP gates

Items are `OPEN` unless their row records approved evidence. The named owner decides; the implementer must not select a default. Until a decision is approved and referenced by the affected artifacts: **STOP the dependent work → REPORT GAP with options/trade-offs → wait for the owner**.

| Decision ID | Status/evidence | Required decision | Decision owner | Blocks |
| --- | --- | --- | --- | --- |
| D-001 | `APPROVED` — [decision record](./docs/product/decisions/D-001-pricing-policy.md), 2026-08-23 | Specificity replacement; V1 non-stacking; one winner per layer; quotation price final; `HALF_UP` by currency precision at line level | Product Owner (user) | Resolved for pricing; D-003 still gates override authority/limits |
| D-002 | `APPROVED` — [decision record](./docs/product/decisions/D-002-inventory-reservation-policy.md), 2026-08-23 | B2C reserves on order creation; B2B reserves on quote-to-order; payment-method expiry only while awaiting confirmation; no auto-expiry after server verification; no renewal; commit on dispatch; V1 no backorder | Product Owner (user) | Resolved as policy; concrete payment methods/TTL values remain D-004 |
| D-003 | `APPROVED` — [decision record](./docs/product/decisions/D-003-discount-quotation-approval-policy.md), 2026-08-23 | Sales `≤5%`; Manager `>5%–15%` or quote `≥100M VND`; Finance `>15%–25%`, quote `≥500M VND` or below approved cost; `>25%` rejected; SoD/revision invalidation/Admin-no-bypass apply | Product Owner (user) | Resolved as policy; D-004 must confirm VND and quotation-total basis |
| D-004 | `APPROVED POLICY` — [decision record](./docs/product/decisions/D-004-commerce-finance-operations-policy.md) | VND/net VAT; method-aware invoice; full-payment COD/bank/gateway slot; 30m/24h TTL; full refund before dispatch; configured/manual single-shipment V1 | Product Owner delegated authority | Policy resolved; concrete rate/fee/provider config gates affected capability |
| D-005 | `APPROVED POLICY` — [decision record](./docs/product/decisions/D-005-identity-authorization-policy.md) | Permission-first scoped RBAC, staff 2FA, separation of duties, dual-control grants and bounded break-glass | Product Owner delegated authority | Policy resolved; exact permission catalog Step 13 |
| D-006 | `PARTIALLY APPROVED` — [decision record](./docs/product/decisions/D-006-nfr-slo-recovery-policy.md) | A2 availability/RPO/RTO, CWV/accessibility, performance and engineering load profile approved | Product Owner delegated authority | Legal retention/residency and actual forecast remain production/privacy inputs |
| D-007 | `APPROVED BASELINE` — [decision record](./docs/product/decisions/D-007-runtime-environment-provider-policy.md) | Laravel/PHP/MySQL/Redis/Node baseline and provider-neutral managed topology approved | Product Owner delegated authority | Concrete provider/account/region/budget bindings remain integration/production gates |
| D-008 | `OPEN` | V2 AI use cases, allowed data classes, provider/model/vector choices, tool allow-list, impact/approval policy, eval thresholds and cost budgets | Product Owner + AI Platform + Security/Privacy | Steps 36–47 and V2 rerun of 48–55 |

Decision records must include approver, date, affected rule/contract revision, alternatives considered and migration/compatibility impact. Numerical limits and provider/model identifiers belong in approved configuration/contracts, never hard-coded business code.

## 7. Gate model

### Entry gate for any implementation step

- Upstream artifacts exist and are approved, not merely proposed.
- Relevant decisions in Section 6 are closed.
- Scope, files, dependencies, rules, risks and tests are recorded with the [AI Task Template](./docs/governance/ai-task-template.md).
- DB/API/Event changes have approved contracts before code/migration.
- Destructive or irreversible work has explicit operation/target approval and recovery evidence.

### Exit gate for any step

- Step-specific DoD in Section 5 passes with evidence.
- Applicable functional, permission, integrity, concurrency/idempotency, integration and E2E tests pass.
- Security, privacy, query/index/N+1, performance and operational impacts are verified proportionately to risk.
- Documentation, ADR, API/Event catalog and runbooks are updated when affected.
- Remaining gaps/risks are explicit; no silent scope expansion.

### V1 launch gate

- Steps `00–35` required by approved V1 scope are `DONE`.
- Steps `48–55` pass for the V1 system.
- AI steps `36–47` remain disabled/not deployed and are not dependencies of commerce.
- Global DoD passes with item-level evidence; no Critical/High security issue or known data corruption issue remains.

### V2 AI enablement gate

- V1 commerce remains operational with all AI flags off and AI providers unreachable.
- Steps `36–47` are approved and pass AI evaluation/security/cost/isolation gates.
- Steps `48–55` are rerun for the AI-enabled release.
- AI workload cannot consume critical commerce worker/connection capacity and no AI tool can bypass domain actions or server authorization.

## 8. Critical verification scenarios

| Area | Required scenario |
| --- | --- |
| Source/plan integrity | Step IDs `00–55` appear exactly once in the register; links resolve; status is evidence-based |
| Architecture | Domain ownership has no cycle; commerce code has no dependency on AI modules/provider SDKs |
| Authorization | Direct HTTP and cross-resource attempts fail server-side regardless of hidden UI |
| Pricing | Approved precedence/rounding is deterministic; historical quote/order snapshots do not change |
| Inventory | Parallel reservations cannot oversell; duplicate release/commit is harmless |
| Checkout/order | Repeated/concurrent submit creates one authoritative result and no partial commerce state |
| Payment | Invalid signatures fail; duplicate/out-of-order webhook/refund attempts do not duplicate money effects |
| Quotation | Accepted revision is immutable; concurrent conversion creates exactly one order |
| Integrations | Timeout/unknown results reconcile safely; retries use stable idempotency identity |
| SEO/UI | Critical public content exists in SSR HTML; canonical/indexation/schema and accessibility checks pass |
| Operations | Realistic load, alert-fire, rollback and restore drills meet approved targets |
| AI V2 | Cross-user isolation, citations, tool authorization, immutable approval, red-team and AI-outage commerce tests pass |

## 9. Initial status summary

| Steps | Initial status | Reason |
| --- | --- | --- |
| `00` | `DONE` | Governance/template revalidated against Promptbook v2.0 with evidence |
| `01` | `DONE` | Audit, risk register and roadmap updated against current DOCX/GitHub baseline |
| `02` | `DONE` | PRD/scope v0.1, V1 single-tenancy and AI-as-V2 boundary approved on 2026-08-23 |
| `03–04` | `DONE` | V1 policy matrix and provider-neutral architecture/NFR baseline approved with evidence |
| `05` | `DONE` | Ownership and acyclic dependency graph approved |
| `06` | `DONE` | Logical ERD/entity catalog approved; no physical schema or migration created |
| `07` | `DONE` | Physical schema/index/concurrency contract approved; no migration created |
| `08` | `DONE` | API conventions/OpenAPI skeleton approved and lint-clean |
| `09` | `DONE` | ADR registry/template and V1 material decisions recorded; AI ADR remains V2 proposed |
| `10` | `DONE` | Coding standards approved with consistent module/layer/examples |
| `11` | `DONE` | Laravel foundation passes local and target PHP 8.5 verification with locked dependencies and no feature schema |
| `12` | `DONE` | Authentication lifecycle, encrypted 2FA, session revocation and security evidence verified on SQLite/MySQL 8.4 |
| `13` | `DONE` | Permission-backed scoped RBAC, dual-control grants/revocation and reviewed break-glass verified on SQLite/MySQL 8.4 |
| `12–20` | `DONE` | Authentication through Cart pass SQLite/MySQL evidence gates |
| `21` | `DONE` | Authoritative Checkout core and provider-neutral Payment/Shipping registration E2E passed; named external providers remain contract-gated |
| `22` | `DONE` | ORD-L1/CANCEL-D1, permissions, audit, Inventory compensation and rollback gates passed |
| `23` | `DONE — PROVIDER-NEUTRAL CORE` | Payment truth, verified callback/idempotency, transactional fact, expiry-safe Order/Inventory binding, late-payment reconciliation and full-refund controls passed; named online provider remains disabled |
| `24` | `DONE — PROVIDER-NEUTRAL CORE` | Configured/manual Shipping, complete Shipment lineage, dispatch/Inventory binding, authenticated tracking and reconciliation gates passed |
| `25` | `DONE — DOMAIN CORE` | Guest/Customer isolation, anti-abuse, pricing snapshots, D-003 approval/SoD, immutable recalculated revisions and lifecycle integrity pass SQLite/MySQL gates |
| `26` | `DONE — TRANSACTIONAL CORE` | Accepted revision converts once with exact snapshots, inventory reservation, Payment/Shipping initialization and rollback/idempotency evidence |
| `27` | `DONE — ARCHITECTURE CONTRACT` | SSR/component/state ownership and the four-surface render/failure-state matrix pass automated fitness and build gates |
| `28` | `DONE — EXECUTABLE PRIMITIVES` | Semantic themes and seven accessible primitives pass contrast, render, focus, reduced-motion, build and full regression gates |
| `29` | `IN PROGRESS — CONTACT/BROWSER GATE` | Public Catalog/Cart/Checkout/Quotation and published CMS Page pass; Contact command and final browser gates remain |
| `30` | `IN PROGRESS — CORE OWN-RESOURCE SLICE` | Profile, own-order and security/session surfaces pass; missing address/company/quote/wishlist/review/notification contracts remain |
| `31` | `IN PROGRESS — CONTRACT/NFR GATES` | Customer/Lead/Company and independent Quote/Order directories pass permission, isolation and cursor gates |
| `32` | `IN PROGRESS — AUDIT SLICE` | Private authorization audit UI passes high-permission, 2FA and evidence-redaction gates; KPI/system surfaces remain |
| `33` | `IN PROGRESS — MYSQL VERIFIED; BROWSER GATE` | Type-specific replacement/unpublish/scheduler, governed media and Admin/public safety pass on SQLite and MySQL; browser gate remains |
| `34` | `IN PROGRESS — LANDING/BROWSER/PRODUCTION GATES` | Canonical/robots/sitemap and approved exact Product schema inventory/composer pass; landing/browser/production gates remain |
| `35` | `IN PROGRESS — PROVIDER/ATTRIBUTION/INTEGRATION GATES` | Provider-neutral Merchant/Analytics delivery and private monitors pass; consent attribution and concrete providers remain gated |
| `36–47`, `55` | `PENDING` | Later steps remain dependency-gated |
| `48` | `IN PROGRESS — OUTBOX CONCURRENCY VERIFIED` | Checkout/Quote conversion, Order, Quotation and Shipment lifecycle, Catalog, Inventory and verified Payment atomically persist versioned facts; relay retry/dead-letter/diagnostic and MySQL two-worker gates pass; remaining approved consumer/retention gates remain |
| `49–54` | `IN PROGRESS` | Readiness/outbox observability and local CI MySQL/concurrency evidence pass; provider dashboards, performance/security, remote deployment and DR exercises remain open |

The immediate next action is to extend the Step `48` catalog only for approved business facts while keeping external Analytics emission blocked until consent/attribution is captured. Step `33` now retains only browser evidence. Public Contact lead capture and attribution remain contract-gated; Steps `30`–`32` retain explicit missing-contract/KPI/NFR sub-gates. Named external providers and missing VAT/shipping configuration remain fail-closed until approved contracts/configuration exist.

## 10. Plan maintenance

- Update this file only when a step status, dependency, decision or release scope changes.
- Link evidence rather than writing unsupported `PASS` claims.
- Preserve Step IDs even when execution order is dependency-corrected.
- Do not mark a parent step `DONE` while a mandatory sub-gate is `PENDING` or `FAIL`.
- Any accepted scope/architecture change updates the PRD/rules/contracts/ADR first, then this plan.
- V2 ideas do not enter the V1 launch scope without an approved Product Requirement and impact review.

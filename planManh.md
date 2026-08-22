# Kaiyo Web — Execution Master Plan

## 1. Document control

- Status: `ACTIVE PLAN — IMPLEMENTATION GATED`
- Created: 2026-08-23 (Asia/Bangkok)
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
| [Engineering governance](./docs/governance/engineering-governance.md) | Exists; Step 00 requires revalidation against Promptbook v2.0 |
| [AI task template](./docs/governance/ai-task-template.md) | Exists; revalidate with governance and Global DoD |
| [Repository audit](./docs/audits/repository-audit.md) | Stale because it predates the two DOCX inputs and says approved specifications are absent |
| [Risk register](./docs/audits/risk-register.md) | Revalidate after updated audit and PRD |
| [Dependency roadmap](./docs/audits/dependency-roadmap.md) | Revalidate after updated audit and PRD |
| [Business Rules Matrix](./docs/business/business-rules-matrix.md) | 47 proposed rules; explicitly unapproved and dependent on Step 02/product decisions |
| [System architecture](./docs/architecture/system-architecture.md) | Proposed/unapproved; revalidate after approved PRD and rules |

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
| 00 | V1 / Governance | AI Engineering Governance | `REVALIDATE` | Promptbook v2.0; no product rule required | Compare governance/template with master prompt, STOP/REPORT GAP, change/destructive/migration/secrets/review policies and Global DoD; preserve stricter rules | Updated governance and AI task template | Section/requirement trace; link and terminology checks | Agent has no right to invent; Global DoD referenced |
| 01 | V1 / Layer 1 | Repository Audit | `REVALIDATE / STALE` | Step 00 | Re-audit after DOCX arrival; correct obsolete “spec absent” claims; refresh risks and dependency roadmap; do not change features | Repository audit, risk register, dependency roadmap | Full repository inventory; mismatch list; working-tree evidence | Every mismatch recorded; no feature code |
| 02 | V1 / Layer 1 | Product Requirements and Scope | `BLOCKED — MISSING` | Steps 00–01; Product Owner approval | Convert v1.1 specification into PRD with personas, JTBD, B2C/B2B use cases, NFRs, V1/V2/out-of-scope and acceptance criteria; lock V1 single-tenant without blocking future tenancy | PRD and scope matrix | Requirement trace to specification; ambiguity/gap review; acceptance-criteria review | V1/V2 explicit; single-tenant decision explicit |
| 03 | V1 / Layer 1 | Business Rules Matrix | `REVALIDATE — PROPOSED/UNAPPROVED` | Step 02; Decisions `D-001–D-005`; Product/Architecture approval | Reconcile 47 proposed rules with approved PRD/spec; remove invented rules; resolve pricing, inventory, approval, tax/shipping/payment and authorization gaps | Approved Business Rules Matrix and decision record | Critical-rule walkthrough; derive unit/permission/concurrency/idempotency cases | Critical rules unambiguous and approved |
| 04 | V1 / Layer 2 | System Architecture | `REVALIDATE — PROPOSED/UNAPPROVED` | Steps 02–03; Decisions `D-006–D-007`; Architecture approval | Reconcile Modular Monolith, edge/runtime/data/adapters and NFR mapping with approved rules; keep commerce independent from AI; record unresolved choices as ADR candidates | Approved diagrams and NFR mapping | Dependency review; failure-mode review; commerce-with-AI-disabled assertion | Commerce core has no AI dependency |
| 05 | V1 / Layer 2 | Domain Boundaries | `PENDING` | Steps 03–04 | Define ownership, public application interfaces, forbidden coupling and events for Identity/CRM, Catalog, Pricing, Inventory, Commerce, Quotation, Payment, Shipping, CMS, SEO, Search, Notification and AI boundary | Domain boundary map | Ownership/coupling review; dependency graph cycle check | No circular ownership |
| 06 | V1 / Layer 2 | ERD | `PENDING` | Steps 03–05; approved entity/state decisions | Create V1 Mermaid ERD and entity catalog; model user/customer/company/contact, warehouse-ready inventory, quote revisions and payment transactions/refunds; mark V2 extensions without implementing them | ERD and entity catalog | Rule-to-entity trace; cardinality/ownership review; god-table and premature-V2 checks | No god table; V2 not implemented early |
| 07 | V1 / Layer 3 design | Database and Index Plan | `PENDING` | Steps 03, 05–06; approved query paths and retention rules | Define schema dictionary, types, FKs, uniques, checks, state representation, soft-delete policy, high-volume queries, locks and idempotency uniqueness; no migration yet | Schema dictionary and index plan | Integrity proof; access-path/index mapping; concurrency/uniqueness review | Critical queries indexed; constraints protect integrity |
| 08 | V1 / Layer 2 | API Contract | `PENDING` | Steps 03, 05–07; approved consumers/integrations | Define `/api/v1` conventions only for approved use cases: authz, pagination, filter/sort, validation/error envelope, idempotency header, rate limiting, versioning, webhooks and OpenAPI strategy | API conventions and OpenAPI skeleton | Contract lint/review; error/idempotency examples; no internal-model exposure | Error and idempotency contract standardized |
| 09 | V1 / Governance | ADR Registry | `PENDING` | Steps 02–08; Architecture approval | Add ADR template and decisions for monolith, delivery UI, DB, Redis/queue, search adapter, object storage, API versioning, AI provider/vector abstraction and V1 tenancy; unresolved decisions remain proposed | ADR template and initial accepted ADRs | ADR status/link review; conflict check against higher-priority rules/contracts | AI must read ADRs before architecture change |
| 10 | V1 / Governance | Coding Standards | `PENDING` | Steps 05, 07–09 | Define domain-local namespaces/folders/naming and Action/DTO/Service/Query/Event/Job/Exception/Test/Migration policies; thin controllers; server validation/policies; no business logic in Blade | Coding standards | Example structure review; consistency check against domain map/ADRs | Two agents can produce consistent code |
| 11 | V1 / Layer 3 | Laravel Foundation | `PENDING` | Steps 00–10; approved runtime versions; early `49`, `51–54` controls | Scaffold approved Laravel/runtime; configure environment, health, correlation logging, tests, lint/static analysis, CI baseline, Redis/cache/session/queue/mail/storage and proxy/cookie security; no feature | Reproducible foundation and CI baseline | Fresh clone boot/test/build; configuration/security checks; no secrets | Fresh boot, tests and build pass |
| 12 | V1 / Layer 3 | Authentication | `PENDING` | Steps 02–03, 07–11; approved identity lifecycle | Implement login/register/verify/reset, session revoke, account disable, throttling and staff 2FA policy; redirect by server-side permission | Authentication module and security tests | Disabled-account, throttle, session-revoke, 2FA and redirect tests | Disabled/staff protections pass |
| 13 | V1 / Layer 3 | RBAC | `PENDING` | Steps 03, 05, 07, 09–12; Decision `D-005` | Implement permission-source-of-truth roles, assignments, policies, approval permissions/limits and server-side scope checks; roles are bundles, not authorization truth | RBAC and permission matrix tests | Positive/negative/cross-scope/direct-request permission tests | Frontend never substitutes server authorization |
| 14 | V1 / Layer 3 | CRM Core | `PENDING` | Steps 03, 05–07, 10–13; approved duplicate/conversion rules | Implement Customer, Company, CompanyUser, Contact, Lead, ownership, duplicate detection, conversion and attribution; conversion transaction-safe | CRM domain and tests | Duplicate/concurrent conversion; ownership/permission; rollback tests | No duplicate conversion |
| 15 | V1 / Layer 3 | Catalog | `PENDING` | Steps 03, 05–07, 10–13 | Implement category, brand, product, variant, media references, attributes/specifications, status and slug redirect events; enforce access paths | Catalog domain and tests | SKU/slug uniqueness; N+1/query-count; status/redirect tests | SKU/slug constraints pass |
| 16 | V1 / Layer 3 | Pricing | `PENDING` | Steps 03, 05–07, 10, 13–15; Decision `D-001` | Implement deterministic PricingEngine using only approved precedence/stacking, tier/customer/promotion rules and immutable snapshots; no UI/controller/AI pricing | PricingEngine and rule tests | Golden calculations, rounding, precedence, snapshot immutability and permission tests | Approved pricing rules fully tested |
| 17 | V1 / Layer 3 | Inventory | `PENDING` | Steps 03, 05–07, 10, 13, 15–16; Decision `D-002` | Implement warehouse-ready stocks, movements and reservations with atomic lock/update and idempotent expiry/release/commit | Inventory domain and concurrency tests | Parallel reservation, duplicate release/commit, rollback and negative-availability tests | Available stock never negative |
| 18 | V1 / Layer 3 | Search | `PENDING` | Steps 04–05, 07, 09–11, 15; approved search requirements | Implement SearchService/Adapter; use DB/full-text only if evidence says sufficient; support approved SKU boost/filter/autocomplete/cache; keep engine replaceable | Search service and adapter tests | Adapter contract, ranking/filter, cache invalidation and source-of-truth tests | No search-engine coupling |
| 19 | V1 / Layer 3 | Media | `PENDING` | Steps 04–10, 11, 13, 15; upload/security/storage decisions | Implement object-storage abstraction, image variants, signed/private documents, quarantine/validation, usage references and malware-scan hook | Media service and security tests | MIME/content mismatch, executable, size/count, signed access and orphan/reference tests | Executable/MIME mismatch rejected |
| 20 | V1 / Layer 3 | Cart | `PENDING` | Steps 03, 07, 12–18 | Implement guest persistent identity, authenticated cart and deterministic merge; reprice/revalidate availability before commitment; never trust stale final price | Cart domain and tests | Guest/login merge, duplicate lines, stale price/stock and concurrency tests | Cart merge deterministic |
| 21 | V1 / Layer 3 | Checkout | `PENDING` | Steps 03, 07–08, 12–20; approved Payment/Shipping ports; final E2E waits for Steps 23–24 | Build orchestration for customer/address/shipping validation, repricing, stock reservation, snapshots and payment intent; idempotent order creation; async side effects | Checkout action, port contracts and tests | Double submit, price/stock conflict, rollback and mocked port tests; final E2E after 23–24 | Double submit safe; E2E gate remains open until 23–24 |
| 22 | V1 / Layer 3 | Order | `PENDING` | Steps 03, 05–08, 13, 16–17, 20–21 | Implement approved order state machine, snapshots, cancellation/refund hooks, policies and audit; prohibit illegal transitions | Order domain and tests | Transition table, stale/concurrent command, cancellation compensation and permission tests | Illegal transitions blocked |
| 23 | V1 / Layer 3 | Payment | `PENDING` | Steps 03, 05–09, 13, 16, 21–22; Decisions `D-004`, `D-007` | Implement payment/attempt/transaction/refund model and provider adapters; signed webhook verification; unique provider event identity; deposit/partial-ready only as approved | Payment domain, adapters and tests | Duplicate/out-of-order webhook, signature, unknown result, refund idempotency and reconciliation tests | Duplicate webhook safe |
| 24 | V1 / Layer 3 | Shipping | `PENDING` | Steps 03, 05–09, 13, 17, 21–22; Decisions `D-004`, `D-007` | Implement method/carrier/shipment/item/tracking abstraction, manual B2B flow and queued carrier sync with timeout/backoff | Shipping domain, adapters and tests | Carrier outage/timeout, duplicate events, manual flow and shipment reconciliation tests | Carrier outage does not block commerce core |
| 25 | V1 / Layer 3 | Quotation | `PENDING` | Steps 03, 05–08, 12–19; Decisions `D-001–D-005` | Implement guest anti-abuse/secure access, approved state machine, immutable revisions, discount approval, pricing snapshots and expiry | Quotation domain and tests | Token/authz, rate limit, approval, revision, expiry and immutable-accepted tests | Accepted revision immutable |
| 26 | V1 / Layer 3 | Quote to Order | `PENDING` | Steps 17, 21–25; approved conversion/reservation rule | Convert one accepted approved revision into one order transactionally; apply inventory rule, idempotency and audit | Conversion action and concurrency tests | Parallel/double conversion, rollback, stock conflict and snapshot tests | Exactly one order per accepted revision |
| 27 | V1 / Layer 4 | Frontend Architecture | `PENDING` | Steps 02–10; domain contracts for each page | Define component taxonomy, state ownership, SSR/interaction/lazy-load/prefetch/code-split/image strategy and complete UX state taxonomy | Frontend architecture, state matrix and render strategy | Duplicate-state-source review; SSR/content and failure-state coverage | SSR ownership clear; states not mixed |
| 28 | V1 / Layer 4 | Design System | `PENDING` | Steps 02, 27; approved brand/accessibility target | Define primitive/semantic/component/page tokens, typography, grids, spacing, motion and accessible component states with examples | Token spec, typography/grid and component catalog | Contrast, keyboard/focus, reduced-motion and token-use checks | Components do not hard-code arbitrary colors |
| 29 | V1 / Layer 4 | Public Website | `PENDING` | Steps 15–21, 25, 27–28; approved sitemap/content | Implement Home, Category, Brand, Product, Search, Cart, Checkout, Quote, Content and Contact through the design system; SSR critical content | Public UI and critical E2E tests | Mobile/desktop, accessibility, SSR/SEO, stock/price conflict and JS/image budget tests | Mobile/desktop pass; Core Web Vitals-aware |
| 30 | V1 / Layer 4 | Customer Portal | `PENDING` | Steps 12–14, 19, 22–26, 27–28 | Implement dashboard, profile, addresses, company, orders, quotes, wishlist/reviews/notifications/security with full failure states | Customer portal and authorization tests | Own-vs-other resource, session expiry, conflict/error and responsive tests | Authorization passes |
| 31 | V1 / Layer 4 | Sales CRM UI | `PENDING` | Steps 13–14, 22, 25–28 | Implement dashboard, leads, Customer 360, company, quotes, orders, tasks, performance and global search with server pagination/filtering | Sales UI and tests | Permission scope, large data/pagination, saved filter and empty/error tests | Large tables optimized |
| 32 | V1 / Layer 4 | Admin UI | `PENDING` | Steps 13–19, 22–28; approved KPI definitions | Implement Commerce, Sales, Content, SEO, Merchant, Analytics and System dashboards; async import/export, drilldown, audit/settings; AI placeholder permission-gated only | Admin UI and tests | Permission, KPI query plan/N+1, async job and audit tests | KPI queries optimized |
| 33 | V1 / Layer 3 | CMS | `PENDING` | Steps 03, 05–07, 10–13, 19, 27–28 | Implement approved Page/Article/FAQ/Banner workflow and email template system; scheduler transitions idempotent | CMS and tests | Workflow/permission, scheduled publish retry, template variable/escaping tests | Scheduled jobs tested |
| 34 | V1 / Layer 4 | Technical SEO | `PENDING` | Steps 02–04, 15, 27–29, 33 | Implement SSR metadata, canonical, robots, sitemap, valid structured data, redirect-chain handling, faceted noindex and approved SEO landing pages | Technical SEO implementation and checks | Rendered HTML, canonical/indexation, schema validity, redirect chain, sitemap/robots tests | No invented structured-data values |
| 35 | V1 / Layer 3/4 | Merchant and Analytics | `PENDING` | Steps 02–08, 15–18, 22–25, 29, 34; Decision `D-007` | Implement retry-safe Merchant lifecycle/batches and approved GA4/GTM commerce/B2B events, attribution and contact clicks; DB is not a GA clone | Merchant/analytics integration and tests | Batch partial failure, price/stock consistency, event dedupe and consent/privacy checks | No duplicate analytics events |
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
| 48 | V1 then V2 rerun / Layer 2 | Event Catalog | `PENDING` | Bootstrap with Steps 05, 08–09; update with each module; approved public/internal distinction | Define central fact-event catalog with version, payload, producer, consumers, delivery, retry, idempotency and PII; events are not commands | Event catalog and version policy | Schema/consumer contract; duplicate/out-of-order consumer tests | Consumers idempotent |
| 49 | V1 then V2 rerun / Layer 6 | Testing Strategy | `PENDING` | Bootstrap after Steps 02–10; suites implemented with every step | Maintain risk-based matrix for unit, feature, permission, concurrency, integration, E2E, SEO, API contracts and AI eval where applicable | Test matrix and executable suites | Critical stock race, double checkout, duplicate webhook, quote conversion and later AI cases | Critical paths automated |
| 50 | V1 then V2 rerun / Layer 6 | Performance Engineering | `PENDING` | Budgets from Steps 02/04; executable system/data profile | Profile before optimization; review DB EXPLAIN/N+1/indexes, cache invalidation, queue priority, CDN/assets and realistic load; isolate later AI workload | Performance report and approved budgets | Load tests with production-like flows/data; query/bundle/queue measurements | Commerce remains stable under approved load and later AI load |
| 51 | V1 then V2 rerun / Layer 6 | Application Security | `PENDING` | Threat model bootstrap after Steps 04–10; updated each module | Cover CSRF, XSS, authz, mass assignment, rate limits, session, webhook, upload, CSP, secrets, dependencies and PII; add AI controls on V2 rerun | Security report and regression tests | SAST/SCA/config/DAST as applicable plus manual threat review | No unresolved Critical/High finding |
| 52 | V1 then V2 rerun / Layer 6 | Observability and SLO | `PENDING` | NFR decisions from Step 02/04; instrumentation from Step 11 onward | Implement correlated metrics/logs/traces for web, DB, Redis, queues, payment, stock and later AI cost/tools/RAG; alerts by symptom/SLO | Observability plan, dashboards, alerts and runbooks | Synthetic failure and alert-fire exercises; correlation trace | Alerts actionable and owned |
| 53 | V1 then V2 rerun / Layer 6 | CI/CD | `PENDING` | Baseline Step 11; expands with Steps 12–52; deployment approval | Pipeline lint/static/test/build/security/contracts and later AI smoke/eval; staging smoke/E2E then approval; migration/worker/cache and rollback strategy | Pipeline and deployment/rollback runbook | Clean reproducible pipeline; staging deploy and rollback rehearsal | Deploy reproducible |
| 54 | V1 then V2 rerun / Layer 6 | Disaster Recovery | `PENDING` | Decisions `D-006–D-007`; schema/storage/environment available | Back up DB, object storage and required configuration metadata with approved encryption/off-site/retention; provider outage and AI-disabled runbooks | DR plan and restore report | Timed restore drill, integrity reconciliation and outage exercises | Restore tested against approved RPO/RTO |
| 55 | V1 launch then V2 controlled enablement / Layer 6 | Production Launch | `PENDING` | All release-scoped steps and Global DoD pass; approvals recorded | Verify DNS/TLS/CDN/WAF, secrets, data stores, queues, storage, mail, integrations, search, SEO, analytics, flags, monitoring and backups; controlled rollout; 24h/7d review; no V2 scope creep in V1 launch | Launch checklist and post-launch review | Smoke/E2E, rollback readiness, alert/backup evidence and Global DoD audit | Global DoD passes |

## 6. Decision register and STOP gates

Every item is `OPEN`. The named owner decides; the implementer must not select a default. Until a decision is approved and referenced by the affected artifacts: **STOP the dependent work → REPORT GAP with options/trade-offs → wait for the owner**.

| Decision ID | Required decision | Decision owner | Blocks |
| --- | --- | --- | --- |
| D-001 | Effective pricing precedence, promotion eligibility/stackability, rounding and customer/company/quotation override behavior | Product Owner + Finance/Pricing owner | Steps 03, 07, 16, 21, 25–26 |
| D-002 | Exact reserve/release/commit moments for B2C orders and B2B accepted quotes; backorder and reservation-expiry policy | Product Owner + Inventory/Warehouse owner | Steps 03, 07, 17, 21–22, 25–26 |
| D-003 | Sales/Manager/Admin discount and quotation-value approval limits, separation of duties and revision behavior | Product Owner + Sales/Finance owner | Steps 03, 07, 13, 16, 25–26 |
| D-004 | VAT/invoice mode, payment methods/terms/deposit/partial/refund scope, shipping fee/manual/carrier/split-shipment behavior | Product Owner + Finance + Operations | Steps 03, 07, 21–26, 35 |
| D-005 | Identity, customer/company ownership, role bundles, permission/resource scopes, delegation and break-glass policy | Product Owner + Security owner | Steps 02–03, 05–08, 12–14, 25, 30–32 |
| D-006 | Numerical NFR budgets: traffic, latency/CWV, availability, queue/search freshness, RTO/RPO, retention, accessibility and data residency | Product Owner + Architecture + Operations/Security | Steps 02, 04, 07, 11, 27–29, 34, 49–55 |
| D-007 | Approved cloud/environment topology and payment, carrier, tax, notification, search, storage and observability providers/contracts | Architecture + Security + Operations + relevant business owner | Steps 04, 08–09, 11, 18–19, 23–24, 35, 50–55 |
| D-008 | V2 AI use cases, allowed data classes, provider/model/vector choices, tool allow-list, impact/approval policy, eval thresholds and cost budgets | Product Owner + AI Platform + Security/Privacy | Steps 36–47 and V2 rerun of 48–55 |

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
| `00` | `REVALIDATE` | Governance exists but must be checked against Promptbook v2.0 |
| `01` | `REVALIDATE / STALE` | Audit predates the newly added DOCX sources |
| `02` | `BLOCKED — MISSING` | No approved PRD/scope matrix exists |
| `03–04` | `REVALIDATE — PROPOSED/UNAPPROVED` | Drafts exist but depend on Step 02 and open business/NFR decisions |
| `05–55` | `PENDING` | Dependency chain and approvals are not satisfied |

The immediate next executable task is Step `00` revalidation, followed by Step `01` re-audit and Step `02` PRD. Feature scaffolding remains blocked until the design/contract gates are satisfied.

## 10. Plan maintenance

- Update this file only when a step status, dependency, decision or release scope changes.
- Link evidence rather than writing unsupported `PASS` claims.
- Preserve Step IDs even when execution order is dependency-corrected.
- Do not mark a parent step `DONE` while a mandatory sub-gate is `PENDING` or `FAIL`.
- Any accepted scope/architecture change updates the PRD/rules/contracts/ADR first, then this plan.
- V2 ideas do not enter the V1 launch scope without an approved Product Requirement and impact review.

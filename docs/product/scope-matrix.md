# Kaiyo Web V1/V2 Scope Matrix

## 1. Status

- Promptbook step: `02 — Product Requirements & Scope`
- Status: `APPROVED — VERSION 0.1`
- Parent PRD: [Product Requirements Document](./product-requirements.md)
- Rule: `V2/Future` means intentionally not in V1; it is not an approved commitment/date.
- Matrix approval confirms V1 inclusion and V2/Future exclusion. Row statuses such as `PROPOSED` or `BLOCKED DECISION` describe implementation/rule readiness and are not a reversal of the approved release boundary.

## 2. Scope matrix

| Capability | V1 | V2 / future | Explicitly outside V1 | Source/gate | Implementation/rule readiness |
| --- | --- | --- | --- | --- | --- |
| Tenancy | One operational business tenant | Future tenancy only through separate requirements/ADR | Multi-tenant UI, billing and tenant operations | Promptbook Step 02 | PROPOSED |
| Guest/public | Browse, search/filter, cart, quote request, contact | AI-assisted discovery only after V2 approval | Anonymous access to protected order/quote/company data | Spec §§2–6 | PROPOSED |
| Customer account | Profile, addresses, company context, orders, quotes, wishlist, reviews, notifications, security | Approved personalization/loyalty only if later required | Cross-customer/company access | Spec §7 | PROPOSED |
| Authentication | Common login, verify/reset/session controls, staff protection | External identity federation only by later ADR | Separate inconsistent login systems | Spec §2 | PROPOSED |
| RBAC | Permission-source-of-truth roles/policies and scoped approval | Additional role bundles as requirements emerge | UI-only authorization or admin bypass | Spec §§2, 24.18; D-005 | BLOCKED DECISION |
| CRM | Customer, Company, contacts/membership, Lead, ownership, attribution, notes/tasks/follow-up | Advanced CRM/ERP integrations | Duplicate/uncontrolled conversion | Spec §§8, 24.2 | PROPOSED |
| Catalog | Category, brand, product, variant, attributes/specs, media/documents, status/slug events | Marketplace/multi-vendor extensions | God-table/unvalidated arbitrary attributes | Spec §§4, 9.3, 24.21 | PROPOSED |
| Pricing | Deterministic engine, retail, approved promotion/tier/customer/quote snapshots | Advanced promotion engine if later justified | Price calculated in controller/UI/AI | Spec §§6.1, 23.5, 24.4; [D-001](./decisions/D-001-pricing-policy.md)/[D-003](./decisions/D-003-discount-quotation-approval-policy.md) | D-001/D-003 APPROVED; D-004 MUST CONFIRM VND/TOTAL BASIS |
| Inventory | Warehouse-ready model operated as approved V1 scope; movement/reservation concurrency | Multi-warehouse operational UI | Backorders; simple mutable `products.stock` as truth | Spec §§23.6, 24.5; [D-002](./decisions/D-002-inventory-reservation-policy.md) | D-002 APPROVED; METHOD/TTL VALUES REQUIRE D-004 |
| Search | Search adapter with DB/full-text when sufficient | External engine, synonyms/ranking at proven scale; AI search in V2 | Hard coupling to a search vendor | Spec §24.10 | PROPOSED |
| Media/upload | Object-storage abstraction, image variants, signed docs, quarantine/validation/scan hook | Additional processing providers | Executable or unvalidated uploads under web root | Spec §§24.17, Promptbook 19 | PROPOSED |
| Cart | Guest/user cart, deterministic merge, reprice/availability notices | Approved personalization only | Stale cart price as final truth | Spec §5, Promptbook 20 | PROPOSED |
| Checkout | Address/shipping validation, reprice, reserve, snapshots, idempotent order/payment orchestration | Additional checkout modes only by requirement | AI/third-party synchronous dependency for critical completion | Spec §5, Promptbook 21; D-001/D-002/D-004 | BLOCKED DECISION |
| Order | State machine, snapshots, audit, controlled cancellation/refund hooks | Advanced RMA/warranty workflow | Arbitrary state mutation | Spec §§23.7, 23.11 | PROPOSED; STATES REQUIRE STEP 03 |
| Payment | Approved COD/bank/gateway, attempts/events/refunds, reconciliation, idempotency; deposit-ready only as approved | Additional providers/terms/currencies | Trusting client status or duplicate webhook effects | Spec §§5, 23.8, 24.7; D-004/D-007 | BLOCKED DECISION |
| Shipping | Manual B2B plus carrier abstraction, shipments/tracking and queued sync | Split/multi-carrier sophistication if approved | Carrier outage redefining order truth | Spec §§23.10, 24.8; D-004/D-007 | BLOCKED DECISION |
| Quotation | Guest anti-abuse/secure access, revisions, approval, immutable accepted revision, expiry, quote-to-order | AI quotation draft assistant in V2 | Autonomous acceptance/final order creation by AI | Spec §§6, 23.3–23.4, 24.3 | PROPOSED; RULE APPROVAL REQUIRED |
| Customer portal | Approved account/company/order/quote/review/notification/security screens | Later approved self-service extensions | Admin/Sales operations | Spec §7 | PROPOSED |
| Sales portal | Leads, Customer 360, companies, quotes, assigned orders, tasks/follow-up, search, performance | AI Sales assistant and advanced CRM in V2 | Unscoped global access | Spec §8 | PROPOSED |
| Admin portal | Commerce, Sales, CMS, SEO, Merchant, Analytics, System, audit/settings, async import/export | AI administration after V2 approval | Permission bypass or synchronous heavy import/export | Spec §9 | PROPOSED |
| CMS | Pages, articles, FAQ, banners and controlled publishing workflow; email templates | Additional content channels/languages | Arbitrary template code execution | Spec §§10, 23.14, 24.16 | PROPOSED |
| Technical SEO | SSR critical content, canonical/robots/sitemap/redirects/faceted rules/landing pages/schema from real data | Expanded tooling based on evidence | Full crawler clone or invented schema data | Spec §11, §§24.11–24.12 | PROPOSED |
| Merchant | Async feed/sync lifecycle, per-product issue visibility, authoritative product data | Additional commerce distribution channels | Treating Merchant as organic ranking guarantee | Spec §12, §24.13 | PROPOSED |
| Analytics | Approved B2C/B2B/contact events and first/last-touch attribution | Advanced warehouse/BI if required | GA clone in commerce DB | Spec §13, §24.14 | PROPOSED |
| Notifications/audit | Configured channels/templates/preferences, required transactional notices, critical audit trail | Additional channels | Disabling required security/transaction notices or logging secrets | Spec §10, §24.16 | PROPOSED |
| Operations | CI/CD, logs/metrics/alerts, queue monitoring, backup/restore and rollback | Multi-region/advanced topology based on NFR evidence | Production edits without pipeline/restore evidence | Spec §§15, 23.18–23.22, 24.22–24.25; D-006/D-007 | BLOCKED TARGETS |
| AI Platform | No runtime AI requirement in V1; feature flags default off | Steps 36–47 after V1 and D-008 approval | AI on checkout/payment/inventory critical path; direct DB/tool bypass | Promptbook §§36–47 | V2 DEFERRED |
| RAG/agents | None required for V1 commerce | Approved Sales/Support/Quotation assistants with citations/tools/evals | Autonomous high-impact commit or cross-user context | Promptbook §§40–47 | V2 DEFERRED |
| Internationalization | Current approved V1 locale/currency only after D-004 confirmation | Multi-language/multi-currency | Premature generalized workflows without requirements | Spec §24.1 | FUTURE |
| Advanced business extensions | Only paths explicitly approved above | Loyalty, affiliate, ERP, marketplace, multi-vendor, advanced RMA/promotion/CRM | Implementation in V1 without new approved requirement | Spec §§23.23–23.24, 24.1 | FUTURE |

## 3. V1 release boundary checks

- V1 cannot depend on any Step `36–47` runtime or provider.
- Future/V2 rows do not create schema/API placeholders unless a V1 contract explicitly requires a minimal extension point.
- “Warehouse-ready”, “deposit-ready” or “provider-neutral” means preserve an approved boundary; it does not authorize unused feature implementation.
- External search/read replicas/microservices require measured evidence and ADR.
- Any scope promotion from V2/Future to V1 requires approved PRD revision, rule/contract impact review and plan update.

## 4. Approval

| Review item | Status | Approver | Date/evidence |
| --- | --- | --- | --- |
| V1 included capabilities | APPROVED | Product Owner (user) | 2026-08-23 conversation approval |
| V2/Future exclusions | APPROVED | Product Owner (user) | 2026-08-23 conversation approval |
| V1 single-tenant boundary | APPROVED | Product Owner (user) | 2026-08-23 conversation approval |
| AI deferred to V2 | APPROVED | Product Owner (user) | 2026-08-23 conversation approval |

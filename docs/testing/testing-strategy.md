# Step 49 — V1 Testing Strategy

## Control

- Status: `BOOTSTRAPPED — EXECUTABLE EVIDENCE PENDING`
- Bootstrap date: 2026-08-23
- Inputs: approved rules/schema/API/ADRs/coding standards and D-006 qualification profile
- Rule: this strategy starts before Step 11 and evolves with every feature; Step 49 becomes `DONE` only when release-scoped suites and evidence pass

## Test layers and ownership

| Layer | Purpose | Runtime/dependencies |
| --- | --- | --- |
| Unit | Value objects, calculations, aggregate transitions/policies | PHP only; deterministic Clock/ID |
| Architecture | Module import allow-list, no cross Eloquent/AI/provider SDK, delivery boundaries | Static reflection/source rules |
| Feature | HTTP/Livewire/session/CSRF/validation/authz/errors/SSR | Laravel test app + isolated DB as needed |
| Integration | MySQL constraints/transactions, Redis, queue/outbox, storage/search/provider adapters | Version-matched services |
| Contract | OpenAPI DTO/Problem/idempotency; public module ports; provider/event schemas | Schema validators and sanitized fixtures |
| Permission | Direct/cross-resource/team/company/disabled/revoked/break-glass | Real policy/query paths |
| Concurrency | Parallel commands and final DB effect count | MySQL, separate processes/connections/barriers |
| E2E | Public/customer/staff critical workflows and failure states | Production-like built artifact/services |
| Non-functional | Load/CWV/accessibility/security/alert/restore/rollback | Staging/ephemeral production-like environment |

## Risk-based matrix

| Risk/flow | Minimum automated evidence before its step exits |
| --- | --- |
| D-001 pricing/promotion | Golden boundary/precedence/non-stacking/equal-priority failure/HALF_UP/snapshot immutability/property cases |
| D-002 inventory | Parallel reserve cannot oversell; duplicate release/commit/expiry one effect; confirmed reservation not expired; Redis loss safe |
| Quote/D-003 | Threshold edges 5/15/25%, 100M/500M VND, below-cost, SoD, stale approval, immutable Sent/Accepted, exactly-one conversion |
| Checkout/Order | Duplicate/concurrent submit one Order/Reservation; price/stock conflict; rollback; ORD-L1 illegal transition/cancel boundary |
| Payment/refund | Invalid signature, replay, duplicate/out-of-order/unknown callback, late success, full-refund idempotency and reconciliation |
| Shipping | Carrier outage does not block core Order; duplicate/out-of-order tracking; append-only pre-delivery correction; one shipment V1 |
| Authorization | Every permission direct positive/negative plus unscoped/cross-scope/direct HTTP; admin no bypass; staff 2FA; revocation/break-glass |
| CRM/privacy | Exact duplicate race, fuzzy review no auto-merge, concurrent lead conversion, resumable privacy tasks and unresolved retention fail closed |
| CMS/SEO/UI | Idempotent scheduled publish, escaping/template variables, SSR HTML, canonical/schema/robots/sitemap, keyboard/focus/AA states |
| Queue/outbox | Crash after commit, duplicate relay/consumer, poison job, bounded retry/dead-letter and correlation trace |
| API | Redocly lint, schema/examples, no numeric ID/ORM leak, cursor tamper, idempotency conflict, safe Problem Details |
| AI isolation | V1 compile/boot/E2E with AI code/providers absent/unreachable |

## Fixtures and isolation

- Factories create valid minimum aggregates through approved builders; raw invalid DB writes are limited to constraint tests.
- Tests never use production PII/secrets/accounts or paid/live provider endpoints.
- Time/ULID/randomness are injectable/frozen. No arbitrary sleep; concurrency uses explicit barriers.
- DB tests run on fresh isolated schema and prove FK/check/unique behavior on MySQL, not only SQLite.
- Each test cleans its own transaction/data without hiding after-commit/queue behavior; those suites use isolated databases/processes.

## CI gates (bootstrap target)

1. dependency/lockfile integrity and secret scan;
2. Pint check, static analysis and architecture tests;
3. unit/feature/permission/contract suites;
4. MySQL/Redis integration and concurrency suites;
5. frontend lint/build and critical SSR/component tests;
6. OpenAPI Redocly spec/recommended lint;
7. dependency/security audits;
8. staging smoke/E2E, accessibility/CWV/load/security as release gates.

Coverage percentage is a diagnostic, not proof. Every critical rule/transition/failure/concurrency case must have explicit trace regardless of aggregate percentage.

## Evidence register

| Evidence | Status |
| --- | --- |
| Strategy/risk matrix | `PASS — BOOTSTRAP` |
| Test runner/config/suites | `PASS — STEPS 11–12`; PHPUnit 12, SQLite feature isolation, MySQL 8.4 migration check, Pint and PHPStan level 8 |
| Authentication critical paths | `PASS — STEP 12`; verification/reset, disabled login, throttle, encrypted TOTP recovery, session scope/revoke and staff gate |
| Authorization critical paths | `PASS — STEP 13`; 54-code catalog, direct/role and global/module/self/resource scopes, cross-scope HTTP denial, dual-control grant/revoke and reviewed break-glass; SQLite + MySQL 8.4 |
| CRM critical paths | `PASS — STEP 14`; exact HMAC identity uniqueness/rollback, fuzzy review-only, cross-scope/stale update, explicit company capability, ownership history and idempotent Lead conversion; full SQLite 34/325 and MySQL CRM 8/30 |
| Catalog critical paths | `PASS — STEP 15`; permission denial, hierarchy cycle/version, atomic Product+Variant, reserved slug/SKU, activation, flat redirects, typed attributes and bounded eager-load queries; full SQLite 40/361 and MySQL Catalog 6/36 |
| Pricing critical paths | `PASS — STEP 16`; five-layer golden replacement, non-stacking, ambiguity failure, VND integer/HALF_UP boundaries, dual-control activation, database eligibility resolution and immutable snapshot; full SQLite 51/389 and MySQL Pricing 11/28 |
| Inventory critical paths | `PASS — STEP 17`; constrained balances, dual-control adjustment, idempotent reserve/release/dispatch/expiry, verified-payment protection, immutable ledger and real two-process no-oversell probe; SQLite Inventory 6/27 and MySQL critical suite/probe |
| Search critical paths | `PASS — STEP 18`; replaceable adapter, exact-SKU ranking, sellability/filter/pagination bounds, literal wildcards, one-query execution and Catalog-fact cache invalidation; SQLite and MySQL Search 5/14 |
| Media critical paths | `PASS — STEP 19`; quarantine-first content detection, mismatch/polyglot/scanner rejection, safe variants, governed private URL, reference protection and orphan cleanup; SQLite Media 5/20 and MySQL combined critical 16/61 |
| Cart critical paths | `PASS — STEP 20`; opaque HMAC guest identity, unique/versioned/idempotent lines, advisory price/stock preview and deterministic locked merge; SQLite Cart 4/16, MySQL combined critical 20/77 and two-process merge probe |
| Checkout critical paths | `PASS — STEP 21`; authoritative repricing/allocation, immutable snapshots, one Order+Reservation retry identity, conflict/fail-closed rollback and explicit-barrier convergence; Steps 23–24 close provider-neutral integration with one Payment/Attempt and one complete Shipment per accepted Order; named providers remain contract-gated |
| Order critical paths | `PASS — STEP 22`; exact ORD-L1 forward transitions, confirmation evidence, stale/illegal denial, dispatch Inventory commit, Customer-scope cancellation request, distinct authorized decision, pre-fulfillment boundary and atomic release/Payment preparation; Order 3/16, full SQLite 78/512, MySQL combined 28/119 |
| Payment critical paths | `PASS — STEP 23 CORE`; one Payment/Attempt per Order, verified manual charge, adapter-authenticated webhook, durable dedupe/conflicting-replay rejection, unknown reconciliation, terminal out-of-order protection, authoritative Order/Inventory binding and dual-control idempotent full refund; Payment SQLite 3/29 plus one MySQL-only skip, full 81/541, MySQL combined 32/150 and focused Payment 4/31 |
| Shipping critical paths | `PASS — STEP 24 CORE`; configured VND fee and complete one-Shipment lineage, permission/version/idempotency lifecycle, dispatch Order/Inventory binding, adapter fail-closed boundary, timeout reconciliation, authenticated monotonic deduplicated tracking and append-only pre-delivery correction; Shipping SQLite 4/30 plus one MySQL-only skip, full 85/571, MySQL combined 37/182 |
| Quotation critical paths | `PASS — STEP 25`; opaque guest HMAC, trusted-context rate limit, Customer isolation, idempotent draft/lifecycle, Pricing/Tax/Shipping snapshots, exact D-003 threshold/SoD, fully recalculated immutable revisions, approval reset, issue/view/accept/reject/expiry and MySQL mutation/deletion guards; full SQLite 100/622, MySQL critical 44/226 and focused Quotation 7/44 |
| Quote-to-Order critical paths | `PASS — STEP 26`; scoped conversion authority, current-Accepted guard, exact pricing/address/Tax/Shipping lineage, one Order/Payment/Shipment/Reservation outcome, stable retry identity, unique revision outcome and complete stock-failure rollback; full SQLite 102/638, MySQL critical 46/242 and focused Quotation/Conversion 9/60 |
| Frontend architecture fitness | `PASS — STEP 27`; Blade SSR/Livewire/Alpine ownership, no delivery-layer persistence access or global business-state store, four delivery surfaces, complete failure-state vocabulary and measurable asset/CWV/accessibility boundaries; full suite 103/663 and production asset build passed |
| Other critical automated paths | `PENDING FEATURES` |
| Staging/non-functional evidence | `PENDING RELEASE` |

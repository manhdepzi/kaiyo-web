# Step 14 — CRM Core Task Record

- Status: `DONE`
- Owner: Codex under Product Owner delegated technical authority
- Inputs: Steps 03, 05–07, 10–13 `DONE`; CRM-D1 and D-005 approved

## Scope

Implement Customer, Company, active Company membership/capability, Contact, Lead, Sales Team, ownership history, privacy-safe exact identity keys, fuzzy duplicate review evidence and transaction-safe idempotent Lead conversion. Privacy erasure workflow remains its later cross-domain implementation gate; no destructive merge is performed automatically.

## Acceptance

- [x] Exact verified email/phone/tax identity is normalized, HMAC-hashed and database-unique while active.
- [x] Fuzzy name similarity produces review evidence only and never auto-merges or blocks by itself.
- [x] Customer/Company/Contact/Lead creation and update use permission+scope and optimistic versions.
- [x] Membership alone grants no company action; explicit active capability is required.
- [x] Ownership history has one active assignment per commercial subject.
- [x] Lead conversion locks the Lead, reuses a confirmed exact party or creates one, and returns one authoritative outcome on retry/concurrency.
- [x] Cross-scope/direct/duplicate/conversion/rollback tests pass on SQLite and MySQL 8.4.

## Verification evidence

- Migration `2026_08_23_000003_create_crm_core_tables.php` creates the CRM roots, active generated-key uniqueness, 17 CRM CHECK constraints and typed resource-scope FKs. It also adds two missing immutable permission definitions without rewriting Step 13 history.
- Full SQLite regression: 34 tests / 325 assertions; CRM slice: 8 tests / 30 assertions.
- MySQL 8.4.3 CRM slice: 8 tests / 30 assertions. The complete schema exposed 35 CHECK constraints and 56 permission definitions.
- MySQL rollback removed every Step 14 table and restored the catalog to 54 definitions. Temporary database `kaiyo_step14_verify_20260823` was dropped after verification.
- PHPStan/Larastan level 8, Pint and Vite production build pass.

The intentionally deferred items are destructive Customer/Company merge and privacy erasure orchestration. They require retention/legal evidence and cross-domain references; Step 14 creates review evidence but performs no automatic destructive merge.

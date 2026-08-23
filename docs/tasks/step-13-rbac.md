# Step 13 — Scoped RBAC Task Record

- Status: `DONE — SQLITE/MYSQL 8.4 VERIFIED`
- Owner: Codex under Product Owner delegated technical authority
- Inputs: Steps 03, 05, 07, 09–12 `DONE`; D-003 and D-005 approved
- Contract: [V1 Permission and Scope Contract](../security/permission-matrix.md)

## Scope

Implement immutable permission reference data, configurable role bundles, direct/role scoped grants, database-authoritative authorization, dual-control high-impact delegation, versioned revocation, staff classification and time-bounded reviewed break-glass. No account, role bundle, grant or production access is seeded.

## Acceptance

- [x] Exact permission catalog and allowed scope mappings are migrated and documented.
- [x] Permission plus resource scope is the only authorization source; default deny and no admin bypass.
- [x] Direct grants and role bundles honor active intervals, status, scope and revocation immediately.
- [x] High-impact delegation and break-glass require distinct eligible approvers and confirmed staff 2FA.
- [x] Grant/revoke/break-glass evidence is append-only and correlation-aware.
- [x] Positive, negative, direct HTTP, cross-scope, stale, expiry and revocation tests pass.
- [x] MySQL migration/rollback, Pint, PHPStan 8, full tests and build pass.

## Boundary

Resource-scope columns are created now, but Customer/Company/Sales Team/Warehouse FKs are added by their owning migrations. Grant actions reject those scope types until an owning target verifier is registered. This avoids placeholder domain tables and preserves explicit-FK ownership.

## Evidence

- Catalog: 54 immutable permission codes and 195 permission/scope mappings; no role, grant, account or production access seeded.
- Full SQLite suite: 26 tests / 289 assertions passing.
- MySQL 8.4.3 authorization suite: 10 tests / 211 assertions passing against real generated columns, CHECK constraints, indexes and locking paths.
- Schema: both migrations and reverse rollback passed; 18 CHECK constraints and 62 index entries observed across the isolated schema.
- Quality: PHPStan level 8 `0 errors`, Pint `PASS`, Vite production build `PASS`.
- Cleanup: temporary database `kaiyo_step13_verify_20260823` was rolled back and removed; no production schema/data changed.

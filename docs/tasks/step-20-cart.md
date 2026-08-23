# Step 20 — Cart Task Record

- Status: `DONE`
- Inputs: Steps 03, 07 and 12–18 `DONE`; D-001/D-002 approved

## Scope

Implement durable guest and authenticated cart identities, unique variant lines, optimistic/idempotent mutation, deterministic login merge and read-time price/availability previews. Cart values are advisory only: checkout must re-run authoritative Pricing and Inventory validation before commitment.

## Acceptance

- [x] Guest identity is opaque, hashed at rest and cannot enumerate another cart.
- [x] One cart has at most one line per Variant; add/set/remove retries are deterministic.
- [x] Login merge has one locked result, sums quantities under Variant scale/limits and never duplicates lines.
- [x] Stale Cart price or availability is clearly marked and never becomes an authoritative order value.
- [x] Concurrent/idempotent mutation and guest-to-account merge tests pass on MySQL.

## Evidence

- Migration `2026_08_23_000008` defines single-active guest/customer identities, unique Variant lines, idempotency outcomes, five MySQL CHECK constraints and two active-owner unique indexes.
- Guest tokens use 256-bit randomness and only keyed HMAC values are stored. Mutations validate Variant quantity scale, lock/version the Cart and reject idempotency-key payload conflicts.
- Merge locks Carts/lines in deterministic order, sums duplicate lines exactly once, terminally marks the guest Cart and is retry-safe.
- Advisory preview recomputes current Pricing and aggregate Inventory availability, records `fresh/stale/unavailable`, and creates no price or stock commitment.
- Four Cart tests/16 assertions pass on SQLite. The combined MySQL 8.4 critical suite passes with 20 tests/77 assertions; a real two-process merge probe converged to one line at quantity 5.0000.
- Step 20 rollback removed all three Cart tables before isolated `kaiyo_test` was dropped; the live `kaiyo` database was untouched.

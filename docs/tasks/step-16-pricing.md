# Step 16 — Pricing Task Record

- Status: `DONE`
- Inputs: approved D-001, D-003, D-004 VND basis; Steps 03, 05–07, 10, 13–15 `DONE`

## Scope

Implement a deterministic server-side PricingEngine, versioned governed configurations, explicit priority/eligibility, V1 non-stacking promotion behavior, specificity replacement, VND integer arithmetic, line-level `HALF_UP`, immutable calculation snapshots and permission/dual-control activation. Tax, shipping and document workflows remain later-domain owners.

## Acceptance

- [x] One eligible winner per layer; equal top priority fails closed.
- [x] Layer order is base → promotion → B2B → customer/company override → approved quotation.
- [x] V1 never stacks promotions/discount layers.
- [x] VND calculations use integers/decimal strings only; quantity line total rounds `HALF_UP`.
- [x] Activation validates ambiguity/interval, requires proposer ≠ approver and pricing authority.
- [x] Calculation snapshot is append-only and later configuration changes cannot alter it.
- [x] Golden, boundary, ambiguity, authorization, snapshot and MySQL rollback tests pass.

## Verification evidence

- Pure engine golden suite proves five-layer replacement, one winner, non-stacking, VND `HALF_UP`, fixed/percentage integer arithmetic and fail-closed invalid/unauthorized inputs.
- Database resolver loads only the active revision and eligible time/quantity/scope rules, applies promotion against the base winner, enforces usage limits, then lets later B2B/override/quotation layers replace it.
- Migration `2026_08_23_000005_create_pricing_tables.php` creates six governed pricing/promotion/snapshot tables, 14 Pricing CHECK constraints and two immutable-snapshot triggers.
- Full SQLite regression: 51 tests / 389 assertions. MySQL 8.4.3 Pricing suite: 11 tests / 28 assertions; complete schema: 64 CHECK constraints and two snapshot triggers.
- MySQL rollback removed every Step 16 table/trigger; temporary database `kaiyo_step16_verify_20260823` was dropped. PHPStan level 8, Pint and Vite build pass.

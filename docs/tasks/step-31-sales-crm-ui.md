# Step 31 — Sales CRM UI Task Record

- Status: `IN PROGRESS — CONTRACT/NON-FUNCTIONAL GATES`
- Started: 2026-08-25
- Inputs: Steps 13–14, 22, 25–28 `DONE`

## Completed slice

- [x] Added a dedicated SSR Sales workspace with noindex and private/no-store delivery.
- [x] Enforced verified authentication, staff 2FA and explicit scoped permissions server-side.
- [x] Added Customer directory with allow-listed status/search filters and cursor pagination.
- [x] Kept Eloquent models out of Blade by using read DTOs.
- [x] Added Customer 360 profile, contact and ownership panels.
- [x] Enforced Customer-scope isolation with authorization-safe `404` behavior.
- [x] Kept Orders and Quotations behind their own `orders.read` / `quotes.read` entitlements.
- [x] Added permission, 2FA, private-header, filtering, cursor and cross-resource feature tests.
- [x] Added Lead directory and governed Lead creation with separate read/create entitlements.
- [x] Added resource-scoped Lead detail and optimistic status/source updates.
- [x] Added idempotent Lead conversion through the existing domain Action without inventing identity-verification evidence.
- [x] Added Company directory/create/detail with scoped access and unverified tax-code handling.
- [x] Added idempotent Company membership delivery that grants no implicit capability.
- [x] Added read-only Sales Quote and Order directories with independent entitlements.

## Current evidence

- `SalesCustomerUiTest`: 9 tests / 71 assertions passed.
- Full suite: 140 passed / 877 assertions; four documented MySQL-only trigger tests skipped on SQLite.
- PHPStan: no errors.
- Pint: passed.
- Vite production build: passed.

## Remaining before Step 31 can be `DONE`

- Quote and Order mutation workflows remain domain-action/entitlement specific; read directories are complete.
- Tasks/performance/global search after their definitions and owning contracts exist.
- Saved filters require an approved persistence contract; URL filters remain canonical meanwhile.
- Query-count, realistic large-data, responsive, accessibility and browser E2E closure.

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
- [x] Hardened the underlying capability boundary before exposing mutation UI: no delegation of unheld authority, no high-impact grant without dual control and successful grants are audited.
- [x] Exposed the backend integration contract without changing UI-owned files: `SalesCompanyView` now returns each member's active `capabilities` plus actor-filtered `delegableCapabilities`; the existing membership route accepts an optional distinct `capabilities[]` list capped at 20.
- [x] Added idempotent audited capability revocation through `DELETE /sales/companies/{company}/members/{member}/capabilities/{capability}` (`sales.companies.members.capabilities.destroy`); the response projection removes the revoked capability and a retry produces no second audit event.
- [x] Added read-only Sales Quote and Order directories with independent entitlements.

## Current evidence

- `SalesCustomerUiTest`: 10 tests / 116 assertions passed; combined CRM/Sales regression is 19 tests / 155 assertions.
- Company detail projection batches member capabilities and delegable grants; the executable query budget remains at or below 18 rather than querying once per member/permission.
- Customer, Lead and Company cursor directories return 20-item pages in exactly two queries; Order and Quote directories use one query. A 25-record-per-directory regression proves stable non-overlapping second pages without query growth.
- Full suite on 2026-08-31: 209 passed / 1,707 assertions; four documented MySQL-only trigger tests skipped on SQLite.
- PHPStan: no errors.
- Pint: passed.
- Vite production build: passed.

## Remaining before Step 31 can be `DONE`

- UI-agent integration may render `delegableCapabilities`, submit `capabilities[]`, display `members[*].capabilities` and call the named DELETE route for removal; backend authorization remains authoritative and high-impact permissions must not be rendered as directly delegable.
- Quote and Order mutation workflows remain domain-action/entitlement specific; read directories are complete.
- Tasks/performance/global search after their definitions and owning contracts exist.
- Saved filters require an approved persistence contract; URL filters remain canonical meanwhile.
- Production-like load remains Step 50; responsive, accessibility and browser E2E closure remains UI-agent owned.

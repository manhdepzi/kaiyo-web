# Step 32 — Admin UI Task Record

- Status: `IN PROGRESS — AUTHORIZATION AUDIT SLICE`
- Started: 2026-08-25

## Completed slice

- [x] Added a private/noindex Admin shell separate from public and Sales surfaces.
- [x] Added high-impact `system.audit.read` permission and staff 2FA gates.
- [x] Added server-filtered, cursor-paginated authorization event reads.
- [x] Excluded before/after snapshot hashes and numeric actor/subject IDs from the view DTO.
- [x] Added direct denial, 2FA and sensitive-field non-disclosure tests.
- [x] Added a private Admin Catalog workspace for Category, Product, Variant, technical specification, image/video and per-product SEO management.
- [x] Kept mutations behind server permissions, staff 2FA, optimistic locks and the existing Catalog/Media application services; newly created Products remain draft until explicitly activated.
- [x] Added a local-only, password-hidden, idempotent administrator bootstrap command with a 2FA enrollment gate and authorization audit evidence.
- [x] Admin Catalog, public Product integration and local administrator provisioning pass 2 focused tests with 42 assertions; the broader Catalog/Media/Public/SEO regression passes 31 tests with 269 assertions.

## Remaining

- Remaining Commerce/Sales/Content/SEO/Merchant/Analytics/System dashboards after approved KPI definitions and owning modules exist.
- Async import/export and job observability.
- Settings write workflows remain behind approved contracts and `system.settings.manage`.
- Query-plan, accessibility, responsive and browser E2E closure.

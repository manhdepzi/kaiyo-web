# Step 29 — Public Website Task Record

- Status: `IN PROGRESS — BROWSER EVIDENCE GATE`
- Started: 2026-08-25
- Inputs: Steps 15–21, 25 and 27–28 `DONE`

## Completed slice

- [x] Replaced the framework welcome screen and external font request with the Kaiyo semantic public shell.
- [x] Added skip link, desktop/mobile navigation, authentication-aware account action and semantic footer.
- [x] Added responsive Home, About and Contact SSR pages without invented contact details, SLAs or commercial claims.
- [x] Added bounded Search with explicit empty state, stable pagination and active Product/Variant/Category/Brand enforcement.
- [x] Added Category, Brand and Product routes with safe 404 behavior for inactive/unknown records.
- [x] Added Catalog application DTOs/reader so public controllers and Blade never access Eloquent.
- [x] Product/Search surfaces explicitly defer price and availability to authoritative selection/commit flows rather than inventing values.
- [x] Public pages compose Step 28 semantic primitives and preserve keyboard/mobile navigation.
- [x] Added opaque HttpOnly guest Cart identity, active-only add/update/remove, deterministic authenticated merge, optimistic-version conflict and explicit unavailable-preview recovery states.
- [x] Added verified-account Checkout UI through the Step 21 action, authoritative repricing/tax/shipping/inventory execution, disabled-configuration states and Customer-owned receipt isolation.
- [x] Added guest/authenticated quotation request UI through the Step 25 actions, B2B bank-transfer policy, anti-abuse handling, submitted lifecycle and session-bound opaque guest access.
- [x] Added public Contact capture into an unowned CRM Lead with server validation, consent evidence, honeypot, rate limiting and operation-key idempotency.
- [x] Expanded the local review catalog to nine products and 27 variants, focused on Van gió and Ống gió, with 20 locally served gallery assets.
- [x] Rebuilt Product detail SSR with category navigation, thumbnail gallery, pointer-aware moderate deep zoom, sticky configuration/CTA card, factual specifications and related products.
- [x] Refined Product detail into a responsive three-column B2B layout with accessible variant cards, synchronized selected configuration, quantity stepper and reduced 1.45x pointer zoom.
- [x] Strengthened Product SEO with unique title/description, canonical, large-preview robots policy, Open Graph/Twitter metadata, descriptive image markup, Product properties and BreadcrumbList JSON-LD without inventing offers or ratings.

## Current evidence

- `PublicWebsiteTest` covers semantic SSR, public Catalog, Cart cookie/idempotency/merge/conflicts, Checkout login/profile/configuration/ownership and guest Quotation submission/access isolation.
- `DemoCatalogPresentationTest` covers idempotent product seeding, gallery/deep-zoom markup, variants, categories and related-product rendering; `PublicContactTest` covers form validation, normalization and idempotent CRM capture.
- Focused Catalog/Search/Public Website suites pass 25 tests/177 assertions; Contact passes 3 tests/23 assertions. Full regression passes 192 tests/1,433 assertions with four intentional environment-specific skips. PHPStan level 8, Pint and the production Vite build pass.

## Remaining before Step 29 can be `DONE`

- Final browser-assisted mobile/desktop, keyboard/accessibility, deep-zoom interaction and query-count evidence.

The live `kaiyo` database was seeded idempotently with the review catalog and migration `2026_08_29_000021` was applied on 2026-08-29. This is review data, not a substitute for the final approved product import workflow.

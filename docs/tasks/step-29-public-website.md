# Step 29 — Public Website Task Record

- Status: `IN PROGRESS — PUBLIC COMMERCE SLICE COMPLETE; CMS GATE OPEN`
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

## Current evidence

- `PublicWebsiteTest` covers semantic SSR, public Catalog, Cart cookie/idempotency/merge/conflicts, Checkout login/profile/configuration/ownership and guest Quotation submission/access isolation.
- Focused public suite passes 13 tests/88 assertions; full regression passes 129 tests/789 assertions with four intentional MySQL-only skips.
- PHPStan level 8, Pint, route registration, `git diff --check` and the production asset build pass.
- Production assets are approximately 33.63 KB CSS and 48.62 KB JavaScript before compression.

## Remaining before Step 29 can be `DONE`

- Published CMS content integration when Step 33 is available; Contact lead submission after the approved CRM command is exposed.
- Final browser-assisted mobile/desktop, keyboard/accessibility and query-count evidence after the CMS/Contact surfaces exist.

The live `kaiyo` database is not migrated by this task. Catalog-backed routes require the approved application schema; schema application to a live database remains an explicit operational action.

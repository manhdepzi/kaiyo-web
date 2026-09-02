# Step 15 — Catalog Task Record

- Status: `DONE`
- Owner: Codex under Product Owner delegated technical authority
- Inputs: Steps 03, 05–07, 10–14 `DONE`; Catalog boundary/schema/index/API conventions approved

## Scope

Implement Category hierarchy, Brand, Product, Variant/SKU, typed filterable attributes, staged media references, status/sellability, immutable slug/SKU reservation, slug redirects and catalog mutation facts. Pricing, inventory, media bytes and search projections remain owned by their later steps.

## Acceptance

- [x] Slugs and SKUs are normalized, case-stable, database-unique and never reused after deactivation/soft deletion.
- [x] Category hierarchy rejects self-reference and cycles.
- [x] Product creation is transactional and produces at least one Variant.
- [x] Attribute definition/value types are allow-listed and values have exactly one owner and one typed column.
- [x] Slug change creates one active redirect fact without a redirect loop.
- [x] Manage/read actions enforce server-side permission; mutable roots use expected versions.
- [x] Listing query uses approved indexes/eager loading and has bounded query count.
- [x] SQLite/full regression and MySQL 8.4 migration/constraint/rollback evidence pass.

## Verification evidence

- Migration `2026_08_23_000004_create_catalog_tables.php` creates nine Catalog tables, reserved ASCII slug/SKU uniques, typed filter indexes, generated active redirect uniqueness, 15 Catalog CHECK constraints and two MySQL category self-reference triggers.
- Full SQLite regression: 40 tests / 361 assertions; Catalog slice: 6 tests / 36 assertions.
- MySQL 8.4.3 Catalog slice: 6 tests / 36 assertions. Complete schema: 50 CHECK constraints; both hierarchy triggers present.
- MySQL rollback removed all Step 15 tables and triggers. Temporary database `kaiyo_step15_verify_20260823` was dropped.
- PHPStan/Larastan level 8, Pint and Vite production build pass.

Step 19 subsequently added the approved `catalog_media_references.media_asset_id` foreign key, uniqueness rule and governed Media aggregate; no placeholder media aggregate was introduced during Step 15.

The Step 32 Admin Catalog slice now exposes permission-gated Category, Product, Variant and typed specification management through the existing Catalog actions. Migration `2026_08_29_000022` adds managed detailed-description and bounded SEO fields without changing Product ownership or lifecycle rules.

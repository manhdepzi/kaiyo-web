# Step 18 — Search Task Record

- Status: `DONE`
- Inputs: Steps 04–05, 07, 09–11 and 15 `DONE`; approved provider-neutral Search Adapter ADR

## Scope

Implement a provider-neutral search contract and DB-backed V1 adapter for products/variants. Search must prioritize exact SKU, support approved category/brand/status filters and bounded pagination, cache only derived results, and invalidate cache from authoritative Catalog changes without treating search/cache as product, price or stock truth.

## Acceptance

- [x] Business callers depend on `SearchService`, never an engine SDK.
- [x] Exact SKU is deterministic and ranks before name/slug matches.
- [x] Filters, stable ordering and bounded pagination are server enforced.
- [x] Inactive/deleted catalog records are excluded from public results.
- [x] Cache keys are normalized/versioned and invalidation removes stale catalog results.
- [x] Adapter contract and ranking/filter/cache/query-bound tests pass on SQLite and MySQL.

## Evidence

- `SearchAdapter` is bound to `DatabaseSearchAdapter`; callers use `SearchService` and no search-vendor SDK exists in domain/business code.
- Five Search tests/14 assertions cover exact SKU ranking, category/brand filters, public sellability, stable bounded pagination, literal wildcard handling, one-query execution and Catalog-event cache invalidation.
- Full SQLite suite passes with 62 tests/430 assertions; PHPStan level 8, Pint and Vite production build pass.
- The combined Inventory/Search suite passed on isolated MySQL 8.4 schema `kaiyo_test` with 11 tests/41 assertions and the schema was dropped afterward; the live `kaiyo` database was not mutated. `phpunit.mysql.xml` keeps this service-backed gate separate from the default SQLite suite.

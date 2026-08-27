# Step 34 — Technical SEO Task Record

- Status: `IN PROGRESS — LANDING/BROWSER/PRODUCTION GATES`
- Started: 2026-08-25
- Boundary: only public facts are emitted; price, stock, ratings and other structured-data values are never inferred.

## Implemented

- [x] Explicit canonical and robots directives in the SSR public layout.
- [x] Search/faceted results use `noindex,follow`; Cart, Checkout and Quotation surfaces use `noindex,nofollow`.
- [x] `robots.txt` protects private surfaces and advertises the sitemap.
- [x] XML sitemap contains static public routes, active Catalog facts and published-only Page/Article facts.
- [x] Product JSON-LD contains only authoritative Product, Brand and Category facts; no invented Offer, price, inventory or rating.
- [x] The approved schema inventory records the source and omission rule for every emitted Product field.
- [x] The Product schema composer is isolated from the controller/view and its decoded payload is asserted exactly.
- [x] Product and Category slug changes create flat redirect facts using their real public paths.
- [x] Old localized and legacy-English Catalog paths resolve directly to the current active public URL; inactive owners fail closed.
- [x] Product sitemap membership follows public sellability (active Category/Brand and at least one active Variant).
- [x] Category/Brand pagination emits stable self-canonical and prev/next navigation; empty overflow pages are noindex.

## Verification evidence

- Technical SEO focused: 5 passed / 51 assertions.
- Full regression: 176 passed / 1235 assertions with four documented MySQL-only trigger skips; PHPStan level 8, Pint and production asset build pass.

## Remaining

- SEO landing-page contract and content ownership.
- Browser-assisted rendered HTML, canonical/indexation and redirect crawl checks.
- Production-host sitemap/robots verification and final Core Web Vitals evidence.

No migration was executed against the live `kaiyo` database in this task.

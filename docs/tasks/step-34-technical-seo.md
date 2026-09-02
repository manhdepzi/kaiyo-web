# Step 34 — Technical SEO Task Record

- Status: `IN PROGRESS — LANDING/BROWSER/PRODUCTION GATES`
- Started: 2026-08-25
- Boundary: only public facts are emitted; price, stock, ratings and other structured-data values are never inferred. Rating facts require approved moderated reviews.

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
- [x] Product SSR emits bounded title/description, large image-preview policy, Open Graph/Twitter metadata and one semantic H1.
- [x] Product JSON-LD now includes published images, active Variant SKU and approved presentation properties; Product breadcrumb emits a canonical `BreadcrumbList`.
- [x] Admin-managed bounded Product title/description override the deterministic fallback; Breadcrumb JSON-LD is decoded in regression tests so Blade directive collisions cannot corrupt structured data.
- [x] Product `AggregateRating` is omitted while no approved review exists and is calculated only from the moderated, verified-purchase public review projection.

## Verification evidence

- Technical SEO plus Admin Product integration: 7 passed / 93 assertions.
- Full regression: 201 passed / 1,589 assertions with four documented environment-specific skips; PHPStan level 8, Pint and production asset build pass.

## Remaining

- SEO landing-page contract and content ownership.
- Browser-assisted rendered HTML, canonical/indexation and redirect crawl checks.
- Production-host sitemap/robots verification and final Core Web Vitals evidence.

Migration `2026_08_29_000022` was applied to the live `kaiyo` database on 2026-08-30 for the bounded Product SEO fields; it does not add inferred SEO facts.

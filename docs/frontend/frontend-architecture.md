# Step 27 — V1 Frontend Architecture

## 1. Control

- Status: `APPROVED AND VERIFIED`
- Date: 2026-08-23 (Asia/Bangkok)
- Inputs: approved PRD, ADR-0002, Steps 05/08/10 and executable application contracts from Steps 11–26
- Stack: Blade SSR route/content ownership, Livewire 4 server interactions, Alpine for local ephemeral behavior, Tailwind 4/Vite
- Boundary: this step defines delivery architecture; it does not implement Step 28 visual tokens or Steps 29–35 feature pages.

## 2. Non-negotiable ownership

| State or decision | Sole owner | Delivery rule |
| --- | --- | --- |
| Price, availability, approval, order/quote/payment/shipping state | Module Application/Domain + MySQL | UI renders returned values and invokes approved Actions; it never derives authoritative truth |
| Route, canonical URL, primary heading/content, initial pagination | Controller/Query → Blade SSR | Critical public content is useful and indexable without JavaScript |
| Interactive form/filter/page state | One Livewire component | URL-worthy filters are query-string state; write state is not duplicated in Alpine |
| Menu/dialog/disclosure/tab focus state | Alpine/local DOM | Ephemeral only; reload may reset it without business loss |
| Authentication and authorization | Laravel middleware + scoped policy/Action | Hidden controls are convenience only; direct requests remain server-authorized |
| Flash/result/conflict state | Action result/exception mapped by delivery | Safe message plus correlation ID; no raw provider/SQL/internal identifiers |
| Cached/search projection | Query adapter | Clearly derived; writes and final commerce decisions always revalidate MySQL truth |

One fact has one writable owner. Blade props, Livewire public properties and Alpine stores must not represent competing writable copies of the same business state.

## 3. Delivery dependency and request flow

```text
Route + middleware
  -> thin Controller or Livewire delivery component
    -> scoped Application Query / readonly View DTO
    -> Application Action / readonly Command DTO
      -> domain policy, transaction, ports and authoritative persistence
  -> Blade SSR / redirect / safe error mapping
```

- Controllers and Livewire components never query or mutate Eloquent business models directly.
- View DTOs expose opaque public IDs, localized display values and explicit capabilities; never numeric IDs, raw lock versions, secrets or provider payloads.
- Every mutation includes CSRF, current actor, server-derived resource scope, expected version where applicable, and a stable idempotency key.
- Post/Redirect/Get is the default for non-Livewire writes. Livewire writes return a refreshed Query DTO; optimistic DOM assumptions never become truth.
- Authorization-safe missing and denied protected resources both render the same `404` surface unless the approved flow requires re-authentication.

## 4. Component taxonomy

| Level | Purpose | Examples | May know domain data? |
| --- | --- | --- | --- |
| Layout | Document landmarks, metadata slots, navigation shell | `public`, `account`, `staff`, `auth` | Only actor/navigation capability DTO |
| Primitive | Semantic accessible element with no business meaning | button, input, dialog, table, pagination | No |
| Pattern | Reusable composed interaction | search box, filter drawer, status banner, confirmation panel | Display DTO only |
| Domain component | One bounded read/write interaction | cart summary, quote approval panel, shipment timeline | Query DTO + one Action boundary |
| Page | Route composition and initial SSR | product detail, order detail, Customer 360 | Coordinates page Queries; no business rules |

Required directories when a slice is implemented:

```text
app/Http/Controllers/{Public,Account,Staff}/
app/Livewire/{Public,Account,Sales,Admin}/
resources/views/layouts/{public,account,staff,auth}.blade.php
resources/views/components/{ui,patterns,domain}/
resources/views/pages/{public,account,sales,admin}/
```

Components are created only when used. Step 28 owns tokens/primitives; feature steps own page/domain components.

## 5. Surface and route inventory

| Surface | Required page families | SSR owner | Interaction owner |
| --- | --- | --- | --- |
| Public | Home, Category, Brand, Product, Search, Cart, Checkout, Quote request/access, Page, Article, FAQ, Contact | Blade for all primary content, price/availability display, metadata and initial results | Livewire for filters/cart/checkout/quote forms; Alpine for drawer/dialog only |
| Customer | Dashboard, Profile, Addresses, Company, Orders/detail/tracking, Quotes/detail, Wishlist, Reviews, Notifications, Security | Blade initial account shell and resource summary | Livewire per bounded form/list/action |
| Sales | Dashboard, Leads, Customer 360, Companies, Quotes, Orders, Tasks, Performance, Global Search | Blade initial scoped summary/table | Livewire server pagination/filter/actions/approval panels |
| Admin | Commerce, Sales, Content, SEO, Merchant, Analytics, System, Audit, Settings, Import/Export | Blade initial permission-filtered navigation and summary | Livewire server tables/jobs/settings; no client KPI calculation |

Page availability is permission-derived server-side. Staff navigation is assembled from effective capabilities; role names and “Admin” labels never authorize.

## 6. Rendering strategy

| Content | Initial SSR requirement | Enhancement |
| --- | --- | --- |
| Product/category/brand/search/content/SEO landing | Title, primary copy, product facts, price/availability label, links, canonical/pagination metadata in HTML | Filters/load-more may update with Livewire while preserving crawlable URLs |
| Cart/checkout/quote | Current summary and form labels/errors in HTML | Livewire submits; server reprices/revalidates before commitment |
| Protected detail/table | Authorized initial summary/table in HTML | Livewire filters, pagination and actions; permission checked every request |
| Dashboard/KPI | Definition, timestamp and server-calculated value | Lazy-load secondary panels only; never conceal missing/error as zero |

- No critical text, navigation or form exists only after client JavaScript.
- Public filters/sort/page are normalized in the URL. Canonical/indexation policy remains Step 34; arbitrary facets are not auto-indexed.
- Livewire lazy loading is allowed only below the initial viewport or for non-critical expensive staff panels and must have a fixed-size skeleton and explicit error/retry.
- Prefetch only safe GET destinations with bounded payloads; never prefetch logout, mutations, sensitive documents or provider redirects.

## 7. Data, pagination and concurrency

- Lists use scoped SQL Queries, allow-listed filters/sorts and server pagination. High-volume lists use keyset/cursor pagination; offset is allowed only for bounded small catalogs with measured query plans.
- URL query state is canonical for public discovery and shareable staff filters. Draft form state stays inside one Livewire component and is not placed in the URL.
- Mutations disable only the submitting control, expose progress through `aria-busy`, and retain entered non-secret values on validation failure.
- A stale expected version produces the Conflict state: show refreshed truth, explain that data changed, and require explicit resubmission. Never silently overwrite.
- Idempotency keys survive safe retry of checkout, quote conversion, payment/refund, import/export and other critical writes.

## 8. Asset and performance strategy

- Vite entry points remain minimal: shared CSS/JS plus route-owned dynamic imports only when a real client enhancement exists. No global SPA/store package.
- Route groups are `public`, `account`, `sales`, `admin`; heavy staff code must not enter the public critical path.
- Images require intrinsic width/height or aspect ratio, responsive `srcset/sizes`, modern variants from Media, meaningful alt text, and lazy loading below the fold. The LCP image is never lazy-loaded.
- Fonts are self-hosted or system-first, subset where licensed, and use `font-display: swap`; icon-only actions require accessible names.
- Budgets: server p95 ≤500 ms/p99 ≤1000 ms under the qualification profile; field p75 LCP ≤2.5 s, INP ≤200 ms, CLS ≤0.1. Route asset/query budgets are measured in Steps 29/50, not declared passed here.
- Redis/search loss may reduce speed or search enhancement but must not prevent authoritative cart, checkout, quote or account reads from MySQL.

## 9. Accessibility, security and privacy

- Target WCAG 2.2 AA: semantic landmarks/headings, keyboard completion, visible focus, skip link, error summary plus field association, live-region status, 44px target guidance and reduced-motion support.
- Loading skeletons are `aria-hidden`; screen readers receive a concise status. Focus moves to the error summary after failed submit and to the new heading after navigation where browser behavior does not.
- Blade escapes by default. Raw HTML requires a dedicated sanitized content type from CMS; no template may render arbitrary HTML/provider payloads.
- Protected responses use `noindex,nofollow`, private/no-store caching where sensitive, and contain no secrets, full tokens or unnecessary PII in DOM/data attributes/logs.
- Signed/private media URLs are obtained on demand from the governed Media action and never embedded in public caches.

## 10. Error and observability contract

- All pages implement the applicable taxonomy in [Frontend State Matrix](./frontend-state-matrix.md).
- Recoverable dependency failures preserve stable page chrome, known authoritative data and a bounded retry action.
- Unknown write outcomes never display success or invite blind duplicate submission; show `processing/reconciling` and poll/query the stable operation identity.
- Delivery telemetry records route name, surface, outcome, duration, query count, Livewire action and correlation ID. It excludes search PII, form bodies, access tokens and high-cardinality resource IDs from metrics.

## 11. Verification and handoff

- Architecture fitness test confirms stack/ownership/taxonomy/page inventory and forbidden duplicated-state/business-logic patterns are explicit.
- Step 28 must implement semantic tokens and accessible primitives consistent with this taxonomy.
- Steps 29–35 must add rendered HTML, authorization, query-count, accessibility, failure-state and asset budget tests per page family.
- Step 49 maintains the executable matrix; Steps 50/51 validate performance and security under realistic conditions.


# ADR-0002 — SSR Delivery Stack

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: D-006, public SEO/accessibility requirements

## Context

Critical product/content/SEO information must be indexable without client JavaScript. V1 also needs rich cart/portal/back-office interactions without maintaining a separate SPA/API product.

## Decision

Use Blade SSR as content/route owner, Livewire for server-driven interactive components, Alpine for small client behaviors and Tailwind through locked compatible packages. Domain/application logic remains outside Blade/Livewire. An API is used only for approved consumers/use cases.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Separate SPA | Rich client ecosystem | Duplicate state/auth/SEO contracts and larger JS budget |
| Blade only | Minimal JS | Excessive custom request/state code for portals/admin |

## Consequences and verification

- Rendered HTML tests cover critical content/meta/canonical/schema.
- WCAG 2.2 AA, keyboard/focus/reduced-motion and D-006 CWV budgets gate release.
- Component requests use normal server authorization/validation; no UI permission truth.

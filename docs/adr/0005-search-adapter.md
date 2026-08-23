# ADR-0005 — Database-first Replaceable Search

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: D-006 ≤5-minute freshness, Catalog/CMS boundaries

## Context

No measured query/ranking/data evidence currently requires an external search cluster. Search must remain replaceable and derived.

## Decision

Start with a `SearchService` application interface and MySQL indexed/full-text adapter where D-006 load/ranking tests pass. Source domains publish projection facts; Search never authorizes writes. Introduce an external engine only after measured failure and a superseding ADR covering provider, cost, rebuild, freshness and rollback.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| External engine immediately | Advanced ranking/scaling | Speculative provider/cost/operations coupling |
| Direct query code everywhere | Minimal abstraction | Prevents replacement and leaks search semantics into domains |

## Verification

Adapter contract, SKU boost/filter/autocomplete, stale/deleted projection, rebuild, source revalidation and freshness/load tests.

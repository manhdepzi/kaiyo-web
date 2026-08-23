# Step 27 — Frontend Architecture Task Record

- Status: `DONE — ARCHITECTURE CONTRACT`
- Inputs: Steps 02–10 `DONE`; Steps 11–26 provide executable domain/application contracts; ADR-0002 defines the Blade SSR/Livewire delivery model

## Scope

Define the V1 delivery architecture for public, customer, Sales and Admin surfaces: component taxonomy, writable-state ownership, SSR boundary, interaction strategy, asset budgets and the complete loading/empty/error/conflict/permission state vocabulary.

## Acceptance

- [x] Blade owns route-level SSR and SEO-critical content; Livewire owns server-backed interaction; Alpine owns ephemeral local UI state only.
- [x] Each datum has exactly one writable owner and no global client business-state store is introduced.
- [x] Primitive, layout, domain and page component responsibilities and directory conventions are explicit.
- [x] Public, customer, Sales and Admin page families have render, pagination, authorization and responsive boundaries.
- [x] Loading, empty, validation, stale/conflict, permission-safe missing, expired session, recoverable dependency, unknown write, terminal and offline states are defined.
- [x] Accessibility, asset, Core Web Vitals, security/privacy, observability and feature handoff rules are measurable.

## Evidence

- `docs/frontend/frontend-architecture.md` is the approved delivery architecture and render-ownership contract.
- `docs/frontend/frontend-state-matrix.md` is the required cross-surface state and recovery contract.
- `FrontendArchitectureTest` guards the selected stack, single writable-owner rule, four surfaces, performance budgets, state taxonomy and forbidden delivery-layer persistence access.
- Full suite passes 103 tests/663 assertions with four intentional MySQL-only skips.
- Production frontend build passes; generated CSS is approximately 43.31 KB and JavaScript 48.62 KB before compression.
- PHPStan level 8, Pint and the HTTP health probe pass.

## Residual boundary

- Step 28 owns executable design tokens and accessible UI primitives.
- Steps 29–35 own page implementations, real route/query integration and surface-specific E2E/accessibility/performance evidence.
- Named external providers remain disabled until their contracts are approved.

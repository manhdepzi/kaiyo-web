# Architecture Decision Record Registry

## Control

- Registry status: `ACTIVE — STEP 09 APPROVED`
- Approval date: 2026-08-23 (Asia/Bangkok)
- Rule: Architecture-changing work must read applicable accepted ADRs first. Conflicts are resolved through a superseding ADR, never silent code divergence.
- Template: [ADR Template](./template.md)

## Status meanings

- `PROPOSED`: review only; not authoritative.
- `ACCEPTED`: authoritative within its scope and higher-priority approved requirements/contracts.
- `SUPERSEDED`: replaced by a linked ADR; retained for history.
- `REJECTED`: considered but not selected.
- `DEPRECATED`: still historical but no longer recommended; migration decision required.

## Registry

| ADR | Status | Decision | Scope/gate |
| --- | --- | --- | --- |
| [ADR-0001](./0001-modular-monolith.md) | `ACCEPTED` | Modular Monolith with domain-local boundaries | V1 architecture |
| [ADR-0002](./0002-ssr-delivery-stack.md) | `ACCEPTED` | Blade SSR + Livewire/Alpine/Tailwind | V1 delivery/SEO |
| [ADR-0003](./0003-runtime-and-mysql.md) | `ACCEPTED` | Laravel 13/PHP 8.5/MySQL 8.4; explicit READ COMMITTED transactions | Foundation/data |
| [ADR-0004](./0004-redis-queue-and-outbox.md) | `ACCEPTED` | Redis derived services; MySQL transactional outbox/durable idempotency | Queue/reliable side effects |
| [ADR-0005](./0005-search-adapter.md) | `ACCEPTED` | DB/full-text first behind Search adapter | Search |
| [ADR-0006](./0006-object-storage-abstraction.md) | `ACCEPTED` | Laravel filesystem/object-storage port, private by default | Media/storage |
| [ADR-0007](./0007-api-versioning.md) | `ACCEPTED` | `/api/v1`, DTOs, Problem Details and durable idempotency | HTTP contracts |
| [ADR-0008](./0008-v1-single-tenancy.md) | `ACCEPTED` | V1 single tenant; no speculative tenant key | Product/data |
| [ADR-0009](./0009-provider-neutral-managed-topology.md) | `ACCEPTED` | Stateless managed/provider-neutral production topology | Environments/operations |
| [ADR-0010](./0010-ai-provider-vector-abstraction.md) | `PROPOSED — V2` | AI gateway/vector/tool abstractions | Blocked by V1 production gate and D-008 |

## Governance checks

- ADRs cannot override approved business rules or contracts.
- An accepted ADR does not authorize account creation, purchase, credentials, production binding, migration execution or destructive operation.
- Stack/provider identifiers live in manifests/configuration/contracts, not domain conditionals.
- Every superseding ADR documents data/contract migration, compatibility, rollback and operational impact.

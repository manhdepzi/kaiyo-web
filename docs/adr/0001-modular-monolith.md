# ADR-0001 — Modular Monolith for V1

- Status: `ACCEPTED`
- Date: 2026-08-23
- Approver: Product Owner delegated architecture selection; Architecture review recorded
- Related: Steps 03–05, [domain boundaries](../architecture/domain-boundaries.md)

## Context

V1 spans tightly coordinated CRM, pricing, stock, quotation, order, payment and shipping invariants. No measured independent-scaling/team/compliance evidence justifies distributed ownership. Distributed transactions would increase failure/reconciliation cost before stable contracts exist.

## Decision

Use one Laravel deployable/codebase with domain-local namespaces, persistence and application ports. Web, worker and scheduler are separate runtime roles of the same artifact. Cross-domain ORM/repository access is forbidden; facts and application ports follow Step 05. Service extraction requires measured evidence and a new ADR.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Microservices now | Independent deployment/scaling | No evidence; adds network consistency, contract and operations cost |
| Unstructured monolith | Fast initial coding | Cannot protect ownership, testing or future extraction |

## Consequences and verification

- Architecture dependency tests reject forbidden imports/cycles and every V1 import of AI/provider SDK code.
- Modules share one DB deployment but not persistence implementations.
- Extraction migration must establish data authority, versioned contracts, reconciliation and rollback first.

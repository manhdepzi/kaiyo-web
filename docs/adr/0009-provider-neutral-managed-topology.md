# ADR-0009 — Provider-neutral Managed Production Topology

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: D006-A2, D007-A1, approved System Architecture

## Context

The D006-A2 target requires recoverable state and replaceable application roles. Concrete accounts/providers/budget are external inputs and must not leak into domain design.

## Decision

Build one immutable artifact deployed to stateless Linux web, worker and singleton-safe scheduler roles behind TLS/CDN/WAF/load balancing. Use operationally owned/managed MySQL, Redis, object storage and backup capable of D006-A2. Environments isolate data/secrets. Every external service is an adapter/config binding; no real binding is enabled without approved contract/account.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| One self-managed production host | Lower initial cost | Weak isolation/recovery and conflicts with A2 evidence expectations |
| Vendor-specific architecture now | Detailed setup | Provider/account/budget facts are missing and would create lock-in |

## Verification

Reproducible build, staging smoke, failure/degradation, rollout/rollback and timed restore drill. This ADR does not authorize production provisioning/deployment.

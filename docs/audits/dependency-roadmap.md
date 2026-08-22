# Dependency Roadmap

## 1. Purpose

This roadmap sequences decisions and technical dependencies revealed by the repository audit. It is not a feature roadmap, delivery estimate, or approval to create business rules, schema, permissions, public contracts, destructive workflows, or stack versions.

Every stage is gated by the source-of-truth precedence and STOP/REPORT GAP policy in [AI Engineering Governance](../governance/engineering-governance.md). Stage completion requires evidence; absence of evidence is not a pass.

## 2. Dependency direction

The approved architectural constraints establish these directions:

```text
Approved requirements / ADRs / contracts
                 |
                 v
Repository + runtime foundation
                 |
                 v
Independent commerce core -----> infrastructure adapters
                 |
                 +-----> SSR public delivery
                 |
                 +-----> integration/search adapters (when approved)

Optional AI capabilities -------> approved commerce interfaces/read models

Forbidden: commerce core -------> AI/LLM provider or AI runtime
```

AI may only be introduced after its use case and boundary are approved. It must not sit on, control, or become required by critical checkout, payment or inventory paths.

## 3. Gated roadmap

### Gate 0 — Approve the baseline

Dependencies: none; this is the current blocker.

Required approved inputs:

- Product requirements, acceptance criteria and explicit out-of-scope list.
- Architecture Specification and relevant ADRs, retaining Modular Monolith as the default unless an ADR approves otherwise.
- Database/API/Event contracts and compatibility policy.
- Authentication flows and role/permission matrix.
- Non-functional requirements for security, accessibility, SEO/indexation/schema, performance/load, availability and data recovery.
- Environment/deployment model and operational ownership.

Exit evidence:

- Versioned documents exist in the repository.
- Conflicts are resolved according to source-of-truth precedence.
- GAP-001 is closed by an authorized decision.

### Gate 1 — Establish the reproducible repository foundation

Dependencies: Gate 0 stack/runtime decisions.

Candidate work, only after approval:

- Scaffold the approved Laravel/PHP and frontend/runtime versions.
- Add dependency manifests and lockfiles, safe example configuration and local environment instructions.
- Define module boundaries for the Modular Monolith.
- Add baseline lint/static analysis, test runner and CI quality gates.
- Establish secret handling and dependency/security scanning.

Exit evidence:

- Clean checkout builds and tests reproducibly.
- No real secret/PII is committed.
- Version and dependency scans are recorded.

### Gate 2 — Implement approved platform and data foundations

Dependencies: Gates 0–1; approved data/auth/async/storage contracts.

Candidate work, only after approval:

- Configure MySQL as system of record and implement approved migrations, constraints and indexes.
- Implement authentication and server-side authorization policies from the approved matrix.
- Configure cache/session/queue/storage according to approved use cases.
- Define transaction, idempotency, retry, timeout and backoff mechanisms for critical writes/jobs/integrations.

Exit evidence:

- Migration/integrity and permission tests pass.
- Critical concurrency/idempotency behaviors are proven by tests.
- Redis/cache is not the source of truth for order, payment or inventory.

### Gate 3 — Build commerce core independently of AI

Dependencies: Gates 0–2; approved commerce rules and contracts.

Required boundary:

- Commerce domain/application code has no import, service requirement, runtime call or deployment dependency on AI/LLM providers.
- Checkout, payment and inventory paths remain fully functional when all AI capabilities are disabled or unavailable.
- Critical writes are transaction-safe; retryable operations are idempotent.

Exit evidence:

- Architecture/dependency test proves no commerce-to-AI dependency.
- Unit, feature, permission, integrity, race/idempotency and E2E critical-flow tests pass.
- Failure tests demonstrate AI outage has no effect on critical commerce flows.

### Gate 4 — Deliver approved public/admin surfaces

Dependencies: approved UX/content/route contracts and applicable foundations/core capabilities.

Candidate work, only after approval:

- Implement server-rendered output for approved SEO-critical public content.
- Implement approved admin interfaces with server-side authorization.
- Add accessibility, SEO/indexation/structured-data and security checks.

Exit evidence:

- Critical accessibility and SEO checks pass.
- Authorization tests cover protected actions.
- Performance budgets are met with recorded evidence.

### Gate 5 — Add approved integrations and search

Dependencies: approved external API/event/search contracts and source-of-truth behavior.

Candidate work, only after approval:

- Implement adapters with explicit timeout, bounded retry/backoff and observability.
- Make retryable writes idempotent and queue jobs retry-safe.
- Ensure search indexes/caches remain derived data rather than commerce truth.

Exit evidence:

- Contract/integration/failure/retry tests pass.
- Reconciliation and recovery behavior follows approved contracts.
- Alerts cover integration failure modes.

### Gate 6 — Introduce optional AI capabilities

Dependencies: stable non-AI path; approved AI use case, ADR, data/privacy contract, evaluation criteria and human-approval rules.

Required boundary:

- AI depends on approved ports/read models; commerce core does not depend on AI.
- AI write tools pass server-side policy, validation and idempotency controls.
- High-impact actions require human approval.
- Prompts, model IDs, provider keys, thresholds and business settings are configuration-managed, not hard-coded.

Exit evidence:

- Dependency test still proves no commerce-to-AI edge.
- AI evaluation and safety regression pass.
- Provider timeout/backoff, degraded mode, auditability and write-action controls are tested.

### Gate 7 — Production readiness and release verification

Dependencies: all gates included by the approved release scope.

Required evidence:

- Global DoD status recorded item by item.
- No unresolved Critical/High security issue or known data corruption issue.
- Load/performance, backup/restore and rollback/deployment runbook verified.
- Monitoring and alerting are active with owners.
- ADRs, API/Event catalog and operational documentation are current.

## 4. Current roadmap status

| Gate | Status | Blocker/evidence |
| --- | --- | --- |
| Gate 0 — Approved baseline | BLOCKED | GAP-001: approved product/spec/contract set absent |
| Gate 1 — Repository foundation | NOT STARTED | Depends on Gate 0 decisions |
| Gate 2 — Platform/data foundation | NOT STARTED | Depends on Gates 0–1 and approved contracts |
| Gate 3 — AI-independent commerce core | NOT STARTED | No commerce requirements or implementation |
| Gate 4 — Public/admin surfaces | NOT STARTED | No approved UX/content/route contracts |
| Gate 5 — Integrations/search | NOT STARTED | No approved contracts |
| Gate 6 — Optional AI | NOT STARTED | No approved AI use case/ADR; must follow stable non-AI core |
| Gate 7 — Production readiness | NOT STARTED | No executable application/release candidate |

## 5. Decision requests before coding

To move beyond audit documentation, the authorized owners must provide or approve:

1. The source-of-truth product/specification set described in Gate 0.
2. The exact stack/version and environment choices.
3. The first explicitly scoped implementation slice and its acceptance criteria.

Until those decisions exist, the safe next action is documentation/discovery only; application scaffolding or feature code would violate the non-invention rule.

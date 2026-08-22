# Repository Risk Register

## 1. Assessment basis

This register records risks evidenced by the repository audit on 2026-08-22. Severity and likelihood are qualitative triage aids, not release acceptance thresholds or invented business settings. Any acceptance/waiver requires an authorized owner and recorded decision.

- Severity: **Critical**, **High**, **Medium**, **Low** based on potential integrity, security, delivery or operational impact.
- Likelihood: **High**, **Medium**, **Low** based on the current repository evidence.
- Status: all risks below are **OPEN** because no mitigation evidence exists yet.

## 2. Risks

| ID | Risk | Evidence | Severity | Likelihood | Mitigation / exit evidence | Dependency/owner role | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| R-001 | Application work may invent requirements or contracts | No approved Product Requirements, ADRs, Architecture Specification, DB/API/Event contracts | Critical | High | Approved, versioned source-of-truth set with resolved conflicts | Product + Architecture | OPEN |
| R-002 | Stack/version and supply-chain posture are undefined | No Composer/npm manifests or lockfiles; no Laravel/PHP/Node evidence | High | High | Approved stack/version decision, reproducible manifests/lockfiles and dependency/security scan | Architecture + Engineering | OPEN |
| R-003 | Data integrity and commerce transaction safety cannot be established | No schema, migrations, constraints, transactions or tests | Critical | High | Approved data contracts; MySQL schema; constraints/indexes; transaction, race and idempotency tests | Architecture + Data/Engineering | OPEN |
| R-004 | Unauthorized access is possible once features are added without an approved authorization model | No auth implementation or approved RBAC matrix | Critical | High | Approved auth/RBAC rules, server-side policies/middleware and permission tests | Product + Security + Engineering | OPEN |
| R-005 | Defects can enter undetected | No automated tests, runner configuration or CI | High | High | Risk-based test suites and required CI gates passing | Engineering | OPEN |
| R-006 | Deployments may be non-repeatable or non-recoverable | No CI/CD, environment definition, deployment or rollback runbook | High | High | Reproducible build/deploy pipeline and verified rollback runbook | Platform/Operations | OPEN |
| R-007 | Data loss recovery is unverified | No database/storage implementation or backup/restore evidence | Critical | Medium | Approved backup policy and successful restore rehearsal evidence | Operations + Data | OPEN |
| R-008 | Production failures may go unnoticed | No monitoring, alerting or incident runbook | High | High | Active telemetry/alerts with ownership and tested response path | Operations | OPEN |
| R-009 | SEO-critical pages may be non-indexable | No rendering layer, routes, metadata or schema checks | High | Medium | Approved SEO page inventory, SSR implementation and indexation/schema verification | Product + Web Engineering | OPEN |
| R-010 | Performance regressions and N+1 queries may remain hidden | No code, schema, access paths, budgets or load tests | High | Medium | Approved budgets; query/index review; profiling and load-test evidence | Product + Engineering | OPEN |
| R-011 | Queue/integration retries could duplicate critical writes | No async/external contracts, timeout/backoff/idempotency design or tests | Critical | Medium | Approved contracts and retry-safe implementation with idempotency/concurrency tests | Architecture + Engineering | OPEN |
| R-012 | Future AI integration could couple into commerce critical paths | Governance rule exists, but no module ADR or automated dependency guard | Critical | Medium | Approved boundary ADR; commerce module has no AI dependency; architecture test; AI evaluations where applicable | Architecture + AI/Commerce Engineering | OPEN |
| R-013 | Secrets or PII handling may be inconsistent | Secrets policy exists, but no environment/config/logging implementation or scan | High | Medium | Approved secret mechanism, redaction, safe example config and security scan | Security + Engineering | OPEN |
| R-014 | Accessibility failures may be discovered late | No UI, accessibility target or test setup | Medium | Medium | Approved target and critical automated/manual accessibility checks | Product + Web Engineering | OPEN |
| R-015 | Repository changes lack durable history/collaboration baseline | Local Git has no commits and no remote | Medium | High | Approved remote/visibility, protected default branch and initial reviewed commit | Repository owner | OPEN |

## 3. Priority dependency chain

The following ordering reflects blockers, not permission to implement:

1. Resolve R-001 before application scaffolding or feature design.
2. Resolve design portions of R-002, R-003 and R-004 before implementing runtime/data/auth foundations.
3. Establish R-005 controls alongside the first executable code, not after feature accumulation.
4. Address R-006 through R-014 according to the approved release scope and Global DoD before production release.
5. Resolve R-012 before introducing any AI runtime dependency or AI write action.

See [Dependency Roadmap](./dependency-roadmap.md) for gated sequencing.

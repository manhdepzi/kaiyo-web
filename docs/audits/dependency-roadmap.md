# Dependency Roadmap

## 1. Purpose and current baseline

This roadmap records executable gates after the 2026-08-23 re-audit. It does not approve a business rule, schema, permission, contract, provider, runtime version or destructive workflow.

Current facts:

- Governance Step 00 is revalidated against Promptbook v2.0.
- Architecture/Functional Specification v1.1 and Promptbook v2.0 are present.
- Git `main` tracks the GitHub remote and has an initial documentation commit.
- Product Requirements v0.1, V1/V2 Scope Matrix, V1 single-tenancy and AI-as-V2 boundary are approved.
- Business Rules Matrix is partially approved at policy-overlay level; System Architecture remains proposed/unapproved.
- The [D-004–D-007 Approval Pack](../product/decision-approval-pack-D004-D007.md) and individual records now contain approved policy/baseline selections; external legal/forecast/provider facts remain downstream gates.
- [D-004](../product/decisions/D-004-commerce-finance-operations-policy.md) and [D-005](../product/decisions/D-005-identity-authorization-policy.md) are approved policy; [D-006](../product/decisions/D-006-nfr-slo-recovery-policy.md) has approved A2/engineering targets; [D-007](../product/decisions/D-007-runtime-environment-provider-policy.md) has approved runtime/A1 baseline.
- [Step 03 Revalidation Report](../business/step-03-revalidation-report.md) records the complete approved V1 policy matrix; contract handoff remains Steps 07–08/13/48.
- [Step 03 Approval Questionnaire](../business/step-03-approval-questionnaire.md) records Product Owner selection `1` for all ten recommended option IDs on 2026-08-23.
- [Step 04 Revalidation Report](../architecture/step-04-revalidation-report.md) records the approved V1 architecture baseline.
- [Step 05 Domain Boundaries](../architecture/domain-boundaries.md) records approved ownership and an acyclic dependency graph.
- [Step 06 V1 ERD and Entity Catalog](../architecture/v1-erd.md) records approved logical aggregates/cardinalities with no physical schema or premature V2 entities.
- Step 07 [Schema Dictionary](../database/schema-dictionary.md) and [Index/Concurrency Plan](../database/index-concurrency-plan.md) approve physical intent, access paths and lock/idempotency behavior without creating migrations.
- Step 08 [API Conventions](../api/api-conventions.md) and [OpenAPI Skeleton](../api/openapi.yaml) are approved and pass Redocly spec/recommended lint; provider addenda remain binding-specific.
- Step 09 [ADR Registry](../adr/README.md) records nine accepted V1 decisions; AI provider/vector abstraction remains explicitly proposed for V2/D-008.
- Step 10 [Coding Standards](../governance/coding-standards.md) defines deterministic module/layer/naming/transaction/query/security/test/migration rules.
- Executable Laravel foundation and Steps 12–26 are implemented; named external providers remain disabled behind approved ports/configuration.

See [Execution Master Plan](../../planManh.md) for the authoritative Step register `00–55` and [Risk Register](./risk-register.md) for risks.

## 2. Dependency direction

```text
Governance (00) [revalidated]
        |
        v
Repository Audit (01) [updated]
        |
        v
PRD + Scope (02) [approved]
        |
        v
Approved Rules (03) -> Architecture (04) -> Domains/ERD/Contracts/ADRs/Standards (05-10)
        |
        v
Foundation and V1 domains (11-35) + continuously built quality controls (48-54)
        |
        v
V1 Global DoD and Production Launch (55)
        |
        v
Optional AI Platform V2 (36-47) -> AI-aware rerun of 48-55
```

Commerce core must not import, require or wait for AI/LLM capability. MySQL remains authoritative for commerce; Redis/search remain derived infrastructure.

## 3. Gates

### Gate A — Re-baseline and product approval

Status: **IN PROGRESS**

- [x] Step 00 governance revalidated.
- [x] Step 01 repository re-audited with current sources.
- [x] Step 02 PRD and V1/V2 scope matrix created and Product Owner approved.
- [x] Decision D-001 pricing precedence/non-stacking/rounding recorded and approved.
- [x] Decision D-002 reservation lifecycle, expiry policy and no-backorder recorded and approved; concrete method/TTL values remain D-004.
- [x] Decision D-003 tiered authority, balanced thresholds, SoD and revision behavior recorded and approved; D-004 must confirm VND/total basis.
- [x] D-004/D-005 policy and D-006/D-007 engineering baselines recorded; external legal/provider inputs explicitly retain affected downstream gates.

Exit: approved PRD/scope and traceable acceptance criteria exist; source conflicts/gaps are explicit.

### Gate B — Design/contracts lock

Status: **GATE A DESIGN LOCK PASSED — STEPS 00–10 COMPLETE; EARLY CONTROLS NEXT**

- Reconcile/approve Business Rules Matrix and System Architecture.
- Define domains, ERD, schema/index plan, API conventions, Event Catalog bootstrap, ADRs and coding standards.
- Bootstrap test/security/observability/CI/DR requirements before feature implementation.

Exit: no critical ambiguous rule; ownership has no cycle; DB/API/Event changes have approved contracts.

### Gate C — Reproducible Laravel foundation

Status: **IN PROGRESS — STEPS 12–26 DONE**

- Scaffold only approved runtime versions.
- Add manifests/lockfiles, safe configuration, health/log correlation, tests/static analysis and CI.
- Configure Redis/cache/session/queue/mail/storage behind approved policies without commerce truth in Redis.

Exit: clean checkout boots, tests and builds; no secrets/PII exposed.

### Gate D — V1 domain and Commerce/B2B core

Status: **PASSED — STEPS 12–26 DONE**

- Implement Steps 12–26 in dependency order with permission, constraint, concurrency and idempotency tests.
- Provider-neutral Checkout E2E is closed by Payment and Shipping registrations; named provider certification remains contract-specific.
- Quote-to-order must create exactly one order and follow approved inventory timing.

Exit: critical commerce flows pass with transaction/integrity evidence and without any AI dependency.

### Gate E — V1 delivery surfaces and growth

Status: **IN PROGRESS — STEP 27 DONE; STEP 28 READY**

- Implement frontend architecture/design system, public/account/Sales/Admin UI, CMS, SEO and Merchant/Analytics.
- SEO-critical content is SSR; all screens include loading/empty/error/conflict/permission states.

Exit: accessibility, SEO/indexation/schema, analytics dedupe and performance gates pass.

### Gate F — V1 production readiness

Status: **NOT STARTED**

- Close Event Catalog, testing, performance, security, observability, CI/CD and DR.
- Verify load, backup/restore, monitoring/alerts and deployment/rollback.

Exit: Global DoD passes; no unresolved Critical/High security issue or known corruption.

### Gate G — AI Platform V2

Status: **DEFERRED until V1 launch and D-008 approval**

- Build provider-neutral, feature-flagged AI bounded context and governed tools.
- High-impact writes require immutable proposal, policy/validation/idempotency and human approval.
- Rerun Event/Test/Performance/Security/Observability/CI/DR/Launch gates for AI enablement.

Exit: AI evaluation/safety/cost/isolation pass and commerce remains operational with AI disabled/outage.

## 4. Immediate dependency queue

1. Implement Step 28 semantic design tokens and accessible primitives against the approved Step 27 ownership/state contracts.
2. Verify contrast, keyboard/focus, reduced-motion and arbitrary-color controls before starting the delivery surfaces.
3. Preserve SSR-critical public content and server-side permission ownership throughout Steps 29–35.
4. Keep named Payment, Shipping and carrier integrations disabled until their provider-specific contracts are approved.

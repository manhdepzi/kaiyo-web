# Repository Risk Register

## 1. Assessment basis

- Updated: 2026-08-23 after Architecture Specification v1.1, Promptbook v2.0 and GitHub baseline were added.
- Severity/likelihood are qualitative triage, not invented release thresholds.
- `OPEN` requires mitigation evidence; `CLOSED` records resolved historical findings; risk acceptance requires an authorized owner and expiry/review date.

## 2. Risks

| ID | Risk | Evidence | Severity | Likelihood | Mitigation / exit evidence | Owner role | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| R-001 | Implementation may proceed without approved critical decisions/contracts | PRD/scope v0.1 and D-001–D-003 are approved, but D-004–D-007 and Steps 03–10 remain open | Critical | High | Approved decision records and reconciled rules/architecture/contracts | Product + Architecture | OPEN |
| R-002 | Stack/version and supply-chain posture remain unproven | Proposed stack only; no manifests/lockfiles/runtime | High | High | Approved ADRs, reproducible manifests/lockfiles and dependency/security scan | Architecture + Engineering | OPEN |
| R-003 | Data integrity/commerce transaction model is undefined | No approved schema/constraints/locks/migrations | Critical | High | Approved rules, ERD/schema/index plan and transaction/concurrency tests | Architecture + Data/Engineering | OPEN |
| R-004 | Authorization scope may be implemented inconsistently | Example roles exist but D-005/permission matrix is open | Critical | High | Approved ownership/permission matrix, server policies and permission tests | Product + Security | OPEN |
| R-005 | Draft lifecycle vocabulary conflicts with source specification | Quote/order states differ between specification and proposed rules | Critical | High | Step 03 reconciliation and Product Owner approval before schema/code | Product + Architecture | OPEN |
| R-006 | Defects can enter without automation | No test runner/suites or CI workflow | High | High | Step 49 strategy bootstrapped with Step 11 CI and per-module suites | Engineering | OPEN |
| R-007 | Deployment/rollback is not reproducible | No application build, environments or pipeline | High | High | Reproducible CI/CD and verified deployment/rollback runbook | Platform/Operations | OPEN |
| R-008 | Backup/restore capability is unknown | No DB/storage infrastructure or drill | Critical | Medium | D-006 targets, DR plan and successful integrity-checked restore drill | Operations + Data | OPEN |
| R-009 | Failures may be invisible | No runtime monitoring, alerts or SLO targets | High | High | D-006 plus active telemetry/owned alert/runbook exercises | Operations | OPEN |
| R-010 | SEO/accessibility requirements may be missed during implementation | Requirements exist, but no page inventory/rendered UI/targets | High | Medium | Approved PRD/page inventory/target and automated/manual checks | Product + Web | OPEN |
| R-011 | Performance/N+1 issues may emerge late | No runtime/data/query plans or numeric budgets | High | Medium | D-006 budgets, index/query review and realistic load evidence | Product + Engineering | OPEN |
| R-012 | Retryable integration writes may duplicate effects | No approved event/provider contracts or implementation | Critical | Medium | Approved contracts, durable idempotency/reconciliation and failure tests | Architecture + Engineering | OPEN |
| R-013 | Future AI could couple into critical commerce | Design prohibits it, but no runtime dependency guard exists | Critical | Low for V1 / Medium for V2 | V2 approval, dependency tests and AI-disabled commerce E2E | Architecture + AI/Commerce | OPEN |
| R-014 | Secrets/PII handling is policy-only | No environment/logging/upload/provider implementation | High | Medium | Approved secret/privacy controls, redaction and security scans/tests | Security + Engineering | OPEN |
| R-015 | Repository lacked durable remote/history | GitHub origin and initial commit `7fb65ca` now exist | Medium | Resolved | Remote/main verification completed | Repository owner | CLOSED |
| R-016 | Binary source documents are difficult to review/diff | Product/specification truth currently resides in DOCX | Medium | Medium | Approved normalized Markdown PRD/rules/contracts with traceability and source hashes | Product + Architecture | OPEN |
| R-017 | Provider/environment choices could be embedded prematurely | D-007 is open; no ADR/contracts | High | Medium | Keep ports/adapters; approve ADR/contracts/config before integration code | Architecture + Security/Operations | OPEN |

## 3. Priority chain

1. Close R-001 and R-005 through D-004–D-007 and Steps 03–10 before schema or feature implementation.
2. Resolve design portions of R-002–R-004, R-012 and R-017 through Steps 04–10.
3. Establish R-006, R-009 and security controls with the first executable foundation, not after features.
4. Verify R-007–R-014 against Global DoD before V1 production launch.
5. Reassess R-013 and rerun Steps 48–55 before enabling any V2 AI capability.

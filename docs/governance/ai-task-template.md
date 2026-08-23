# AI Task Template

> Dùng template này cho mọi AI engineering task. Không xóa mục bắt buộc. Nếu không áp dụng, ghi `N/A` và lý do. Không đánh dấu `PASS` nếu chưa có bằng chứng.

## Task metadata

- Promptbook Step ID / layer:
- Release (`V1`/`V2`/cross-cutting):
- Task ID / link:
- Title:
- Owner / requester:
- Reviewer / approver:
- Status:
- Created / updated:
- Source revision(s)/hash(es):

## Source of truth

- Approved Business Rules / Product Requirements:
- ADRs:
- Database / API / Event contracts:
- Architecture Specification:
- Existing production code:
- Current task prompt:
- Conflicts or gaps found:

> Áp dụng thứ tự precedence trong [AI Engineering Governance](./engineering-governance.md). Nếu thiếu quyết định về business rule, schema/state, permission, public contract hoặc destructive workflow: **STOP → REPORT GAP → nêu lựa chọn/trade-off → chờ phê duyệt**.

## Scope

### In scope

-

### Out of scope

-

### Acceptance criteria

- [ ]

## Files

### Expected files/modules

-

### Actual files changed

-

## Dependencies

- Internal modules/services:
- External systems/APIs:
- Packages/runtime/infrastructure:
- Upstream/downstream task dependencies:

| Required upstream artifact/decision | Required revision/status | Evidence/link | Gate (`OPEN`/`PASS`/`BLOCKED`) |
| --- | --- | --- | --- |
|  |  |  |  |

> Không bắt đầu implementation khi dependency/approval bắt buộc chưa `PASS`. Draft `PROPOSED/UNAPPROVED` không mở implementation gate.

## Rules

- Approved business rules:
- Authorization/permission rules:
- Data integrity/state-transition rules:
- API/event/compatibility rules:
- Relevant ADR/architecture constraints:
- Idempotency/concurrency requirements:
- AI safety/human-approval requirements:

## Data and contract impact

- Database/schema/migration:
- Data backfill/integrity:
- Public API:
- Events/webhooks:
- Backward compatibility:
- Deployment/rollback implications:

## Risks

| Risk | Impact | Likelihood | Mitigation / evidence | Owner/status |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## Plan

### OBSERVE

- [ ] Repo/docs/ADR/contracts/tests/current implementation inspected
- [ ] Existing user changes and repository state checked

### PLAN

- [ ] Scope, files, dependencies, rules, data changes and risks recorded
- [ ] Test and verification plan recorded
- [ ] Gaps reported and approvals captured
- [ ] Artifact dependency gate checked; technical execution order recorded if different from Step ID order

### IMPLEMENT

- [ ] Changes kept small and within approved boundary
- [ ] No unapproved business rule/schema/state/permission/public contract introduced

### TEST

- [ ] Tests selected according to risk

### VERIFY

- [ ] Build/lint/tests and relevant operational checks executed

### REPORT

- [ ] Diff, evidence, remaining risks and Global DoD status reported

## Test

| Test type | Scenario / requirement | Command or method | Expected result | Actual result / evidence |
| --- | --- | --- | --- | --- |
| Unit |  |  |  |  |
| Feature |  |  |  |  |
| Authorization/permission |  |  |  |  |
| Concurrency/idempotency |  |  |  |  |
| Integration |  |  |  |  |
| E2E critical flow |  |  |  |  |
| AI evaluation/safety |  |  |  |  |

## Verification

| Check | Command or method | Status (`PASS`/`FAIL`/`PENDING`/`N/A`) | Evidence / reason |
| --- | --- | --- | --- |
| Build |  |  |  |
| Lint/static analysis |  |  |  |
| Migration/constraints/integrity |  |  |  |
| Query/index/N+1 |  |  |  |
| Security/secrets/PII |  |  |  |
| Accessibility |  |  |  |
| SEO/SSR/indexation/schema |  |  |  |
| Performance/load |  |  |  |
| Backup/restore |  |  |  |
| Monitoring/alerting |  |  |  |
| Deployment/rollback runbook |  |  |  |

## Diff

### Summary by file/boundary

-

### Requirement-to-change mapping

| Acceptance criterion / rule | Files/change | Test/evidence |
| --- | --- | --- |
|  |  |  |

### Scope confirmation

- Scope expansion: `None` / describe approved expansion and decision link
- Unrelated user changes preserved:
- Known limitations / remaining risks:

## Global Definition of Done

> Tham chiếu danh sách chuẩn tại [AI Engineering Governance — Global Definition of Done](./engineering-governance.md#10-global-definition-of-done). Mỗi mục cần bằng chứng hoặc lý do `N/A`.

| Global DoD item | Status (`PASS`/`FAIL`/`PENDING`/`N/A`) | Evidence / reason |
| --- | --- | --- |
| Functional requirements/acceptance criteria pass |  |  |
| Authorization & permission tests pass |  |  |
| Database constraints/integrity pass |  |  |
| Race condition & idempotency tests pass cho critical flows |  |  |
| Automated tests + E2E critical flows pass |  |  |
| Accessibility critical checks pass |  |  |
| SEO/indexation/schema checks pass |  |  |
| Performance budget & load test pass |  |  |
| Security review: không Critical/High unresolved |  |  |
| Secrets/PII không bị lộ |  |  |
| Backup và restore đã test |  |  |
| Monitoring + alerting active |  |  |
| Rollback/deployment runbook verified |  |  |
| Documentation/ADR/API/Event catalog cập nhật |  |  |
| Không có known data corruption issue |  |  |
| AI evaluation/safety regression pass nếu thay đổi AI Platform |  |  |

## Approvals and gaps

### REPORT GAP records

| Gap / conflict | Sources checked | Options and trade-offs | Blocking scope | Decision/status |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

### Required approvals

- Destructive change approval:
- Irreversible migration approval:
- Public contract/compatibility approval:
- High-impact AI action human approval:
- Accepted risk/waiver and expiry:

| Approval scope | Approver/authority | Date | Approved revision/evidence | Status |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## Final report

- Outcome:
- Tests executed and results:
- Verification results:
- Deployment/rollback notes:
- Remaining risks/blockers:
- Documentation/ADR/API/Event catalog updates:

# D-006 — V1 NFR, SLO, Recovery, Accessibility and Data Policy

## 1. Decision control

- Status: `PARTIALLY APPROVED — LEGAL RETENTION/REAL FORECAST INPUTS OPEN`
- Decision ID: `D-006`
- Decision owners: Product Owner + Architecture + Operations/Security
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval authority: Product Owner delegated selection of the most reasonable option to the implementation agent; balanced package and engineering qualification profile selected
- Source options: [Decision Approval Pack D-004–D-007](../decision-approval-pack-D004-D007.md#5-d-006--nfr-slo-performance-recovery-accessibility-and-retention)
- Blocks: Steps 04, 07, 11, 27–29, 34 and 49–55
- Does not approve: infrastructure capacity, provider contract, retention deletion, production deployment or destructive operation

## 2. Approved release standards

- Accessibility: WCAG 2.2 Level AA for V1 release surfaces.
- Field Core Web Vitals at the 75th percentile: LCP at most 2.5 seconds, INP at most 200 milliseconds and CLS at most 0.1.
- Critical/listing paths have no N+1 and high-volume queries require measured access paths/query plans.
- Capacity claims require an approved production-like traffic/data profile and load evidence.

These are V1 release targets. A failed target requires measured remediation or an explicit risk acceptance; it is not silently relaxed.

## 3. Availability/recovery selection

| Package | Monthly availability | RPO | RTO | Owner decision |
| --- | --- | --- | --- | --- |
| `D006-A1` Lean | 99.9% | 1 hour | 4 hours | Not selected |
| `D006-A2` Balanced (recommended) | 99.95% | 15 minutes | 2 hours | `APPROVED` |
| `D006-A3` Critical | 99.99% | 5 minutes | 30 minutes | Not selected |

## 4. Approved engineering qualification profile

This is a minimum design/load-test profile selected for engineering qualification, not a claim about actual business demand. A later approved forecast may raise it but cannot silently lower release evidence.

- Normal dynamic traffic: 50 requests/second sustained for 30 minutes.
- Campaign peak: 200 requests/second for 5 minutes without integrity loss; graceful overload controls must preserve critical writes.
- Concurrent authenticated/public sessions: 500; concurrent checkout/payment attempts: 50.
- Planning-horizon data: 100,000 sellable variants, 100,000 CRM customer/company records and 1,000,000 combined order/quotation records.
- Import job: 10,000 rows; Merchant/feed batch: 100,000 items; both resumable and outside request paths.
- Application-controlled dynamic SSR/API reads: p95 at most 500 ms and p99 at most 1,000 ms under the qualification profile, excluding user network and third-party latency.
- Application-controlled checkout/domain commands: p95 at most 1,000 ms before any external redirect/async provider completion.
- Critical queue work starts within 60 seconds at p95; search projection freshness within 5 minutes; notification work starts within 2 minutes at p95.
- Planned maintenance allowance: at most two hours per month during an approved low-traffic window and still counted according to the selected availability policy unless the final SLO contract explicitly excludes it.

## 5. Approved technical retention baseline and open legal input

- Application/debug logs: 30 days; performance telemetry: 90 days; failed-job payloads: 30 days, with PII minimized/redacted.
- Recoverable daily backup retention: 35 days; weekly recovery points: 12 weeks; all encrypted/scoped and failure-domain independent.
- Session/revocation evidence follows the approved authentication contract; expired session material must not grant access.
- Order, invoice, payment, quotation, audit and legally relevant CRM retention remains `TBD — LEGAL/FINANCE APPROVAL REQUIRED`. No automatic destructive deletion is allowed until jurisdiction, legal hold and retention periods are approved.
- Data residency remains `TBD — SECURITY/PRIVACY APPROVAL REQUIRED`. Production provider/region cannot be bound, and cross-border transfer cannot be assumed, until approved.
- Privacy erasure therefore follows `PRIV-D1` and fails closed/manual-review for any data class with unresolved retention.

## 6. Approval record

| Standards decision | Package | Traffic/data profile reference | Retention/residency reference | Approvers | Date |
| --- | --- | --- | --- | --- | --- |
| WCAG 2.2 AA, CWV, no-N+1/query-plan, latency/queue/search budgets | `D006-A2` Balanced | Section 4 engineering qualification profile | Section 5 technical baseline; legal retention/residency still open | Product Owner delegation; agent selected balanced package | 2026-08-23 |

## 7. Activation gate

The measurable engineering/SLO package is approved and may drive architecture/testing. Legal retention/residency and actual business forecast remain external inputs; affected destructive privacy operations and production-region/provider selection stay blocked. Architecture, testing, performance, observability and DR gates must link to these targets rather than restating unsupported `PASS` claims.

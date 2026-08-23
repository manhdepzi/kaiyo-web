# Step 52 — V1 Observability and SLO Plan

## Control

- Status: `BOOTSTRAPPED — INSTRUMENTATION/ALERT EXERCISES PENDING`
- SLO source: D006-A2 (99.95% monthly; RPO 15m; RTO 2h) and performance budgets
- Provider/tool binding: open under D-007; instrumentation uses standard application interfaces/config

## Signal model

| Area | Metrics/logs/traces/business signals |
| --- | --- |
| HTTP/SSR/API | request rate, status, latency, body-limit/rate result, route template; correlation ID |
| MySQL | connection/pool saturation, query latency, slow/query-count, deadlock/lock wait, replica/backup status if used |
| Redis | availability/latency, cache hit, queue broker errors; never imply commerce truth health alone |
| Queue/outbox | oldest age, wait/run, retry/failure/dead, unpublished dispatch count/age |
| Commerce integrity | reservation conflict/expiry, checkout idempotency conflict, conversion duplicates prevented, illegal transitions |
| Payment/refund | verified/failed/unknown, invalid signature/replay, reconciliation backlog/age, refund outcome |
| Shipping | dispatch/tracking delay, invalid/out-of-order events, correction/exception backlog |
| Search/Merchant/analytics | projection freshness, rebuild/failure, batch partial failure/dedupe |
| Security | login/2FA/revoke, permission denials, break-glass grant/use/expiry, upload rejection, secret/scan findings |

## Logging/tracing rules

- Structured JSON outside local interactive development.
- Request/job/correlation/operation/fact IDs propagate HTTP → action → dispatch → job → adapter.
- Redact passwords/tokens/cookies/authorization/signatures, raw provider payloads, full email/phone/address and sensitive financial details.
- Metrics labels are bounded and never include user/order/quote IDs. IDs belong in restricted logs/traces.
- Audit records are separate restricted evidence, not copied wholesale into debug logs.

## SLO/alert bootstrap

| Symptom | Initial alert intent | Owner/runbook |
| --- | --- | --- |
| Availability/error-budget burn | fast/slow burn on successful eligible HTTP requests | Web/Operations; incident/rollback runbook |
| Latency budget breach | sustained p95/p99 route-class breach | Application/DB investigation |
| Queue/outbox age | critical age > approved budget or dispatch backlog rising | Worker/Redis/MySQL runbook |
| Payment unknown/reconciliation | count/oldest age exceeds configured operational threshold | Finance/Engineering reconciliation |
| Inventory invariant/deadlock | any negative-invariant attempt; elevated conflict/deadlock | Commerce integrity incident |
| Backup/restore evidence stale | backup failure or scheduled restore proof absent | Operations/DR owner |
| Security signal | Critical/High scanner finding, webhook forgery burst, break-glass use | Security incident owner |

Numerical alert thresholds beyond approved SLO budgets remain deploy-time configuration tuned through exercises, not hard-coded application constants.

## Step 11 minimum

- correlation middleware and log context for HTTP/jobs;
- `/up` liveness plus safe readiness/diagnostic command without public dependency/secret disclosure;
- exception/error reporting adapter interface with local/log implementation;
- queue failure/outbox metrics hooks;
- environment validation for log level/channel and no production debug.

Dashboards, actionable alerts, synthetic failure and alert-fire evidence remain pending provider binding/executable system.


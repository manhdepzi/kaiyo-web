# Step 52 — V1 Observability and SLO Plan

## Control

- Status: `IN PROGRESS — LOCAL DEPENDENCY PROBE PASSED; INSTRUMENTATION/ALERT EXERCISES PENDING`
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
| Queue/outbox age | critical age > approved budget or dispatch backlog rising | [Transactional Outbox Runbook](./outbox-runbook.md) |
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

## Local executable evidence

- Local targets: application `http://127.0.0.1:8000`, MySQL `127.0.0.1:3306/kaiyo` and Redis `127.0.0.1:6379` from the `kaiyo-redis` container.
- Migrations `000001`–`000018` ran successfully as batch 1 on the empty local `kaiyo` schema; no fresh/reset/rollback command was used.
- `/ready` is stateless: session, cookie and request-forgery middleware are excluded while correlation and route binding remain.
- Enabled MySQL and Redis checks return HTTP 200 with bounded `application`, `database` and `cache` results.
- A simulated cache exception returns sanitized HTTP 503 without the exception detail or any session/XSRF cookie.
- Foundation health suite: 6 passed / 17 assertions; Outbox suite: 7 passed / 45 assertions; full regression: 182 passed / 1268 assertions with four documented MySQL-only skips.
- The local `kaiyo` schema applied migrations `000001`–`000018`; home, search and readiness HTTP smoke checks pass.
- `php artisan schedule:list` confirms the CMS publication and outbox relay jobs are registered every minute while Redis is healthy.
- `php artisan outbox:status --json` reports bounded state counts and oldest ages without payload/error details; alert exit codes activate only with deployment-supplied age/dead-record gates.
- Disposable schema `kaiyo_step48_verify_20260827` ran all 18 migrations and the two-process outbox probe: workers split 6/6 and all 12 facts reached `published` with exactly one attempt. The exact disposable schema was then dropped; live `kaiyo` retained all 18 migrations and an empty healthy outbox.

Dashboards, actionable alerts, synthetic failure and alert-fire evidence remain pending provider binding/executable system.

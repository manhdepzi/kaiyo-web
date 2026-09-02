# Step 50 — V1 Performance Budgets

## Control

- Status: `BOOTSTRAPPED — MEASUREMENT PENDING`
- Source: approved D006-A2 engineering qualification profile
- Rule: profile before optimization; no unsupported capacity PASS

## Approved budgets

| Area | Budget/gate |
| --- | --- |
| Public/admin dynamic server response | p95 ≤500ms, p99 ≤1000ms under qualification profile, excluding user network/provider completion |
| Domain checkout command | application-controlled p95 ≤1000ms before external redirect/async completion |
| Field CWV p75 | LCP ≤2.5s, INP ≤200ms, CLS ≤0.1 |
| Load | 50 RPS sustained 30m; 200 RPS 5m peak; 500 sessions; 50 concurrent checkout/payment attempts |
| Critical queue start | p95 ≤60s |
| Notification work start | p95 ≤2m |
| Search projection freshness | ≤5m |
| DB | no N+1; critical queries mapped to index and `EXPLAIN ANALYZE`; no unaccepted full scan/temp/filesort |
| Pagination | keyset for high-volume; max API page 100 |
| Data profile | 100k variants, 100k CRM subjects, 1m combined orders/quotes, 10k-row import, 100k-item Merchant batch |

## Measurement plan

- Track route/action/query count, DB time/locks/deadlocks, Redis time/hit ratio, queue wait/run/retry, external timeout/outcome and asset/CWV metrics.
- Load mixes public browse/search, login/portal, cart, checkout, quote, staff tables and background batches with realistic distributions.
- Integrity assertions run during/after load: no oversell, duplicate order/payment/refund, lost dispatch or inconsistent snapshots.
- AI workload is absent for V1; V2 reruns with separate capacity/pools.

The read-only local dependency baseline is recorded in [runtime-baseline.md](./runtime-baseline.md). Production-like load, CWV and capacity evidence remains pending until the approved representative data profile and target environment exist.

## Guarded public-read load definition

`tests/load/v1-public-read-qualification.js` encodes the D006-A2 public-read portion of the qualification profile: 50 RPS for 30 minutes, then 200 RPS for five minutes, with server-response p95/p99 gates. It is intentionally not a production command:

```powershell
# Harmless local, 30-second smoke only (k6 must be installed separately)
k6 run tests/load/v1-public-read-qualification.js

# Explicitly approved staging qualification only
$env:BASE_URL = 'https://approved-staging.example'
$env:ALLOW_NONLOCAL_TARGET = '1'
$env:PROFILE = 'qualification'
$env:CONFIRM_QUALIFICATION = 'KAIYO_D006_A2'
k6 run tests/load/v1-public-read-qualification.js
```

The script rejects unknown profiles, non-HTTP targets, non-local targets without explicit consent and qualification traffic without its confirmation token. It creates no commerce records and covers only public/readiness reads. Authenticated, checkout/payment, queue and representative-data integrity tests still need an approved isolated dataset, test identities and external-provider stubs before they can contribute release evidence.

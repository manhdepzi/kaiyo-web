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

Runtime report remains pending until executable system and representative data exist.


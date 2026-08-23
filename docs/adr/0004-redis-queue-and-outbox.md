# ADR-0004 — Redis Derived Services and MySQL Transactional Outbox

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: INV-001/004/008, Step 07 lock/idempotency plan, Step 48 pending payload catalog

## Context

Queues/caches improve responsiveness but delivery is at least once and Redis cannot atomically commit with MySQL business changes. A domain commit must not silently lose its required side effect.

## Decision

- Redis backs Laravel queue, cache, rate limiting, approved sessions and best-effort coordination; it is never sole commerce/authorization/idempotency truth.
- Insert a versioned `dispatch_records` outbox row in the same MySQL transaction as the domain fact. A relay claims committed rows with `SKIP LOCKED`, publishes/jobs them and stores attempts/outcome.
- Consumers and jobs use MySQL durable operation/event identity and tolerate duplicate, delayed and out-of-order delivery.
- Failed jobs/dead-letter evidence is observable; destructive purge requires separate approval.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Dispatch directly after commit | Simple | Process crash can lose required side effect |
| Redis lock/idempotency only | Fast | Redis loss can duplicate/corrupt authoritative effects |
| Distributed transaction | Atomic systems | Unsupported/complex with providers and unnecessary |

## Verification

Crash between commit/relay, duplicate relay/consumer, Redis flush/outage, poison job and reconciliation tests prove no truth loss/duplicate business effect.

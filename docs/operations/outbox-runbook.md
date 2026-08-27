# Transactional Outbox Runbook

## Scope and ownership

- Applies to `dispatch_records` and the scheduled `outbox:relay` process.
- Primary owner: Application Operations; the producing domain owner investigates invalid/poison facts.
- A `published` record means the synchronous internal consumer boundary returned successfully. Every downstream consumer still owns durable idempotency.
- No purge, payload rewrite, attempt reset or manual state transition is authorized by this runbook.

## Normal operation

1. The business action inserts a versioned fact in the same MySQL transaction as its authoritative state.
2. Laravel Scheduler runs `php artisan outbox:relay` every minute under `withoutOverlapping()` and `onOneServer()`.
3. MySQL relay workers claim eligible rows with `FOR UPDATE SKIP LOCKED`; an expired `publishing` lease becomes eligible again.
4. Success becomes `published`; failures use bounded exponential retry and become `dead` after the configured attempt ceiling.

Configuration is environment-owned and bounded in code:

- `OUTBOX_CLAIM_LEASE_SECONDS` (default 300, minimum effective 30);
- `OUTBOX_RETRY_BASE_SECONDS` (default 30, minimum effective 1);
- `OUTBOX_MAX_ATTEMPTS` (default 8, minimum effective 1).

## Detection

- Use the private Admin **Outbox** monitor; access requires `system.audit.read`, verified account and confirmed staff 2FA.
- Watch pending count/oldest age, records stuck in `publishing`, dead count and error-code concentration.
- Never copy payload JSON or hashes into tickets, chat, metrics labels or public logs.

## Triage

1. Confirm Scheduler is running and only the expected release is deployed.
2. Confirm MySQL health/locks and application error logs using correlation/fact public IDs.
3. Confirm the affected consumer and any provider dependency without changing commerce state.
4. For an intermittent outage, run a bounded relay once: `php artisan outbox:relay --limit=100`.
5. Verify pending age falls and the same fact identity does not create duplicate consumer effects.

## Escalation

- `dead` record: page Application Operations and the producing/consuming domain owners.
- Order/Payment/Inventory inconsistency or duplicate money/stock effect: treat as a commerce integrity incident; stop the affected consumer rollout.
- Growing backlog with healthy MySQL: inspect consumer latency/failure before increasing workers or limits.

Requeueing a dead fact, changing payload/version, shortening retention or deleting evidence requires an approved recovery operation with exact targets, compatibility assessment and rollback/reconciliation proof.

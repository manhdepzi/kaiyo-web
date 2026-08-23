# ADR-0003 — Supported Runtime, MySQL Authority and Transaction Isolation

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: D-007, Step 07 schema/concurrency contract

## Context

The application needs a supported reproducible runtime and one transactional source of truth. Critical flows use explicit row locks/expected versions and benefit from avoiding unnecessary MySQL gap-lock behavior.

## Decision

- Laravel 13, PHP 8.5, supported Composer 2.x stable, Node.js 24 LTS, MySQL 8.4 LTS and Redis 8.2 families; exact supported compatible patches are locked in manifests.
- MySQL/InnoDB is authoritative for business state, durable idempotency, audit and dispatch facts.
- Application transactions use `READ COMMITTED` plus explicit row locks/compare-and-swap as defined by Step 07. Connection/session initialization and tests must prove the isolation level.
- UTC `DATETIME(6)`, VND decimal money and named DB constraints follow the schema dictionary.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| MySQL default REPEATABLE READ | Familiar default | More gap-lock/deadlock risk; code already requires explicit locks/current-state validation |
| PostgreSQL | Strong feature set | Conflicts with approved MySQL baseline and adds no demonstrated V1 benefit |

## Compatibility, rollback and verification

Isolation/config change is exercised with stock/checkout/quote/payment concurrency tests. If a driver/provider cannot honor it, Step 07 is re-reviewed; no silent fallback. Database upgrade/rollback requires backups, compatibility test and restore evidence.

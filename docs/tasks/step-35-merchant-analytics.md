# Step 35 — Merchant and Analytics Task Record

- Status: `IN PROGRESS — PROVIDER/ATTRIBUTION/INTEGRATION GATES`
- Started: 2026-08-26
- Boundary: provider integrations consume approved, authoritative commerce facts and may never become a second source of truth.

## Implemented

### Merchant delivery

- [x] Provider-neutral `MerchantDestination` contract with an explicit disabled adapter as the safe default.
- [x] Durable batch/item lifecycle with stable operation identity, payload conflict detection and visible partial failures.
- [x] Feed values are rebuilt from active Catalog variants, the Pricing resolver and summed Inventory availability at processing time.
- [x] Failed items are retryable without duplicating successful delivery identities.
- [x] Queue job uses the isolated `integrations` queue; the private Admin workspace requires `merchant.manage` and confirmed staff 2FA.

### Analytics delivery

- [x] Exact V1 event allow-list for product view, cart add, checkout start, order placement, quotation request, lead creation and contact click.
- [x] Consent fails closed before provider invocation.
- [x] Raw email, phone, name, address, IP and user-agent attributes are rejected at the policy boundary.
- [x] Destination/event identity is unique across batches; conflicting replays fail closed and provider failures remain visible/retryable.
- [x] Stored delivery evidence contains bounded attributes and payload hashes rather than copying external analytics data into the commerce database.
- [x] Provider-neutral `AnalyticsDestination` contract uses an explicit disabled adapter by default.
- [x] Private read-only Analytics monitor requires `analytics.read`, confirmed staff 2FA and `no-store, private` responses.
- [x] B2C Checkout and B2B Quote conversion persist versioned `commerce.order.placed` facts atomically through the ADR-0004 outbox; rollback leaves no orphan fact.
- [x] Verified Payment persists a versioned `payment.verified` fact without raw financial references; reliable consumption confirms the Order or opens late-payment reconciliation.
- [x] Scheduled relay uses durable claims, stable event identity, bounded retry, stale-lease recovery and dead-letter evidence.
- [x] Inventory adjustment/reserve/release/commit/expiry now persists `inventory.availability.changed` atomically with a versioned Stock Balance; the fact contains only opaque Variant/Warehouse identities, change type and balance version so future Merchant/observability consumers must rebuild from Inventory truth.
- [x] Approved Order forward transitions and cancellation now persist `commerce.order.state.changed` atomically with the Order version; exact command retries cannot duplicate facts and the payload excludes customer, payment, shipment, actor and evidence details.

## Verification evidence

- Merchant and Analytics focused: 8 passed / 51 assertions.
- Full regression: 182 passed / 1268 assertions; four MySQL-only immutable-trigger tests are documented skips on SQLite.
- PHPStan level 8, Pint, production asset build and `git diff --check` pass for the implemented slice.

## Remaining

- Add remaining approved domain producers/consumer mappings; no external provider call may run inside a commerce transaction.
- Approve and implement first/last-touch attribution and the public contact-click command contract.
- Assign production reconciliation/alert ownership and prove backlog thresholds against approved SLOs.
- Configure concrete Merchant/GA4/GTM adapters only after D-007 provider credentials/contracts are approved; validate them in staging, never in automated tests against live endpoints.
- Complete MySQL integration, browser consent, production-host, privacy and realistic batch/load evidence.

No migration was executed against the live `kaiyo` database in this task.

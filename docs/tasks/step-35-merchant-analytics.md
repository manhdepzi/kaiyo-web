# Step 35 — Merchant and Analytics Task Record

- Status: `IN PROGRESS — PROVIDER/BROWSER/STAGING GATES`
- Started: 2026-08-26
- Boundary: provider integrations consume approved, authoritative commerce facts and may never become a second source of truth.

## Implemented

### Merchant delivery

- [x] Provider-neutral `MerchantDestination` contract with an explicit disabled adapter as the safe default.
- [x] Durable batch/item lifecycle with stable operation identity, payload conflict detection and visible partial failures.
- [x] Feed values are rebuilt from active Catalog variants, the Pricing resolver and summed Inventory availability at processing time.
- [x] Failed items are retryable without duplicating successful delivery identities.
- [x] Queue job uses the isolated `integrations` queue; the private Admin workspace requires `merchant.manage` and confirmed staff 2FA.
- [x] Released Catalog and Inventory facts create one replay-safe refresh intent without calling a destination in the relay transaction.
- [x] `merchant:process-refreshes` claims a bounded batch, expands authoritative Catalog scopes, coalesces identical revisions, and emits explicit `upsert` or `remove` projection operations.
- [x] Refresh evidence is durable per request/variant; successful unchanged revisions are skipped, failures back off, and the fifth failed attempt becomes terminal `dead`.
- [x] The scheduled worker is overlap-protected and the disabled projection destination fails visibly without mutating Catalog, Pricing or Inventory truth.

### Analytics delivery

- [x] Exact V1 event allow-list for product view, cart add, checkout start, order placement, quotation request, lead creation and contact click.
- [x] Consent fails closed before provider invocation.
- [x] Raw email, phone, name, address, IP and user-agent attributes are rejected at the policy boundary.
- [x] Destination/event identity is unique across batches; conflicting replays fail closed and provider failures remain visible/retryable.
- [x] Stored delivery evidence contains bounded attributes and payload hashes rather than copying external analytics data into the commerce database.
- [x] Provider-neutral `AnalyticsDestination` contract uses an explicit disabled adapter by default.
- [x] Private read-only Analytics monitor requires `analytics.read`, confirmed staff 2FA and `no-store, private` responses.
- [x] Same-origin JSON contracts record versioned consent and bounded attribution without raw URL/query, IP, user-agent or direct identity fields.
- [x] Consent evidence uses an encrypted HTTP-only SameSite=Lax cookie; expiry and a newer same-session decision invalidate older evidence.
- [x] Delivery resolves consent from server truth, rejects caller boolean spoofing and reconstructs only allow-listed first/last-touch fields.
- [x] Lead creation, quotation request and checkout order placement persist one minimized `analytics_event_intents` row inside the authoritative business transaction; exact business retries cannot duplicate it.
- [x] `analytics:process-intents` claims a bounded batch with stale-lease recovery, resolves consent at processing time and delivers through the provider-neutral boundary with exponential retry and a five-attempt terminal bound.
- [x] Missing, denied, expired or revoked consent is terminally suppressed without invoking the destination; configured-provider failure remains visible and cannot roll back CRM, Quotation or Checkout truth.
- [x] Read-only `growth:delivery-status` reports bounded Merchant/Analytics state counts, oldest backlog/lease ages and safe error-code concentration; optional age/dead gates return monitoring-friendly exit codes without exposing identities or payloads.
- [x] B2C Checkout and B2B Quote conversion persist versioned `commerce.order.placed` facts atomically through the ADR-0004 outbox; rollback leaves no orphan fact.
- [x] Verified Payment persists a versioned `payment.verified` fact without raw financial references; reliable consumption confirms the Order or opens late-payment reconciliation.
- [x] Scheduled relay uses durable claims, stable event identity, bounded retry, stale-lease recovery and dead-letter evidence.
- [x] Inventory adjustment/reserve/release/commit/expiry now persists `inventory.availability.changed` atomically with a versioned Stock Balance; the fact contains only opaque Variant/Warehouse identities, change type and balance version so future Merchant/observability consumers must rebuild from Inventory truth.
- [x] Approved Order forward transitions and cancellation now persist `commerce.order.state.changed` atomically with the Order version; exact command retries cannot duplicate facts and the payload excludes customer, payment, shipment, actor and evidence details.

## Verification evidence

- Merchant and Analytics focused: 9 passed / 55 assertions, including stale-lease recovery.
- Analytics consent/delivery/intent focused: 13 passed / 86 assertions.
- Growth delivery status: 3 passed / 17 assertions; live local report is healthy with zero pending/processing/dead rows.
- Full regression: 229 passed / 1,849 assertions; four MySQL-only immutable-trigger tests are documented skips on SQLite.
- PHPStan and Pint pass; migrations through `000032` are applied to the local MySQL `kaiyo` database and both Growth scheduled commands are registered.

## Remaining

- Wire the remaining browser-originated product-view, cart-add, checkout-start and contact-click contracts; these remain UI-agent-owned at their capture boundary.
- Assign production alert recipients and prove deployment-owned backlog thresholds against approved D-006 SLOs.
- Configure concrete Merchant/GA4/GTM adapters only after D-007 provider credentials/contracts are approved; validate them in staging, never in automated tests against live endpoints.
- Complete MySQL integration, browser consent, production-host, privacy and realistic batch/load evidence.

Migrations `000028–000032` were executed against the local `kaiyo` database; `000032` adds provider-neutral, consent-resolved Analytics intent evidence without adding a named provider.

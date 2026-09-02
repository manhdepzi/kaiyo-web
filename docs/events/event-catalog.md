# V1 Event Catalog Bootstrap

- Status: `IN PROGRESS — SEVEN INTERNAL FACT SCHEMAS LOCKED; RETENTION/REMAINING CONSUMERS OPEN`
- Owner: Architecture + affected domain owner
- Rule: events are immutable facts, never commands; consumers must be idempotent and must not infer missing business data.

## Analytics delivery allow-list

| Fact name | Intended authoritative producer | Delivery state | Minimum subject |
| --- | --- | --- | --- |
| `catalog.product_viewed` | Public Catalog delivery after a successful public Product response | Producer pending | Product public identifier |
| `cart.item_added` | Cart action after commit | Producer pending | Cart/Variant public identifiers |
| `checkout.started` | Checkout application boundary after accepted validation | Producer pending | Customer/Cart public identifiers |
| `order.placed` | Checkout/Order in the authoritative transaction | Durable producer implemented | Order public identifier |
| `quotation.requested` | Quotation in the draft-creation transaction | Durable producer implemented | Quotation public identifier |
| `crm.lead_created` | CRM in the Lead-creation transaction | Durable producer implemented | Lead public identifier |
| `contact.clicked` | Approved public contact action | Contract pending | Contact channel identifier |

The Step 35 delivery boundary validates this allow-list and requires server-resolved consent evidence. First/last-touch attribution is rebuilt from bounded, allow-listed, consent-scoped touches; caller-provided consent booleans cannot authorize provider emission. Lead, quotation and order producers now persist replay-safe intent inside their business transaction. Browser product-view, cart-add, checkout-start and contact-click capture remains pending and UI-agent-owned.

## Internal reliable facts

| Fact | Version | Producer transaction | Aggregate/payload | Delivery |
| --- | --- | --- | --- | --- |
| `commerce.order.placed` | `1` | B2C Checkout or accepted Quotation conversion | Order public ID; Order/Reservation public IDs and source (`checkout` or `quotation`) | Same-transaction `dispatch_records`; scheduled relay; at-least-once consumer contract |
| `commerce.order.state.changed` | `1` | Approved Order transition or approved cancellation decision | Order public ID; previous state, resulting state and Order version | Same-transaction `dispatch_records`; scheduled relay; downstream consumers must rebuild protected details from Order truth |
| `catalog.projection.changed` | `1` | Brand, Category, Product or Variant mutation | Aggregate type/public ID/version, change type and bounded changed-field references | Same-transaction `dispatch_records`; scheduled relay; idempotent Search cache invalidation consumer |
| `inventory.availability.changed` | `1` | Approved adjustment or reservation reserve/release/commit/expiry | Variant public ID; Warehouse public ID, Stock Balance version and bounded change type | Same-transaction `dispatch_records`; scheduled relay; future Merchant/observability consumers must rebuild from Inventory truth |
| `payment.verified` | `1` | Server-verified manual/reconciliation evidence or authenticated provider callback | Payment public ID and SHA-256 operation identity; no raw provider/bank reference | Same-transaction `dispatch_records`; reservation expiry protection in the producer transaction; idempotent Order confirmation/reconciliation consumer |
| `quotation.revision.state.changed` | `1` | Governed Quotation transition, customer/guest access transition or Quote-to-Order conversion | Quote public ID; revision number/version and bounded previous/resulting states | Same-transaction `dispatch_records`; scheduled relay; future Notification/CRM/Analytics consumers must resolve authorized context from Quotation truth |
| `shipping.shipment.state.changed` | `1` | Governed warehouse action, carrier booking result, authenticated tracking event or approved correction | Shipment public ID; Shipment version and bounded previous/resulting states | Same-transaction `dispatch_records`; scheduled relay; future Notification/Analytics consumers must resolve authorized details from Shipping truth |

`commerce.order.placed` is an internal business fact and is not the external Analytics event name. An Analytics consumer may map it to `order.placed` only after it can prove the applicable consent revision and approved attribution data.

`commerce.order.state.changed` is emitted after the governed Order state/version advances, inside that same transaction. Its version-derived identity makes an exact command retry harmless. The payload excludes customer, actor, payment, shipment and evidence references; an outer rollback removes the transition operation, state mutation and fact together.

`catalog.projection.changed` contains public identifiers rather than numeric database keys. Attribute mutations lock and version their owning Product/Variant, so repeated changes cannot collapse into an unversioned projection fact. Search invalidation runs from the released fact and is therefore recoverable through the relay.

`inventory.availability.changed` is emitted only after the authoritative Stock Balance version advances and in the same transaction. Its payload deliberately excludes quantities, adjustment reasons and internal keys. Consumers receive enough identity to rebuild current availability and must not treat the fact payload as stock truth. A retry of the same reservation/terminal operation emits no duplicate version; an outer rollback removes both the mutation and fact.

`payment.verified` is persisted atomically with the immutable charge and protects an active reservation from expiry before commit. Its consumer confirms a Pending Order through the governed Order action; a terminal reservation produces an idempotent reconciliation case instead of silently confirming or repeatedly failing the relay.

`quotation.revision.state.changed` follows the immutable revision state/version, including the Accepted-to-Converted transaction. Repeated view evidence while already Viewed creates access evidence but no false state-change fact. Its payload excludes guest tokens, Customer/company identity, commercial values, addresses, approval evidence and actor identity.

`shipping.shipment.state.changed` records only actual Shipment state changes. A failed booking that remains Ready emits no false change; unknown outcomes, authenticated tracking, approved corrections and delivery use the resulting Shipment version as stable identity. Tracking references, carrier payloads, addresses, actor and Order/Customer identity are excluded.

All seven internal facts pass through the centralized `DispatchFactCatalog` before persistence. The catalog enforces the exact type, version, aggregate family, key set and bounded value domain for each payload; unknown fields, mismatched public identifiers and unapproved state/change values fail before an outbox row can be written. This makes the documented payload allow-list executable and prevents a producer from silently adding raw personal or provider data.

## Executable consumer coverage

`php artisan outbox:consumer-coverage --json` is the source of operational evidence for current V1 internal consumers. It validates that the registry declares exactly every fact in `DispatchFactCatalog`, shows only fact type/owner/consumer labels, and never reads payloads. `--require-all-covered` returns failure while an approved fact has no current consumer.

| Fact | Owner | Current consumer coverage |
| --- | --- | --- |
| `catalog.projection.changed` | Catalog | Search cache invalidation; Merchant refresh intent |
| `commerce.order.placed` | Checkout/Order | **None yet — explicit Step 48 gap** |
| `commerce.order.state.changed` | Order | Customer in-app Order notification |
| `inventory.availability.changed` | Inventory | Merchant refresh intent |
| `payment.verified` | Payment | Order confirmation or reconciliation |
| `quotation.revision.state.changed` | Quotation | Customer in-app Quotation notification |
| `shipping.shipment.state.changed` | Shipping | Customer in-app Shipment notification |

The direct Analytics intent created by Checkout is not a consumer of `commerce.order.placed`: it is committed with the authoritative Checkout transaction to preserve consent evidence and must not be recreated by outbox replay. A future consumer for that fact requires a separately approved idempotency and consent contract.

## Classification and ownership

| Fact | Data classification | Current consumer/owner | Retention state |
| --- | --- | --- | --- |
| `commerce.order.placed` | Internal operational; opaque commerce IDs, no customer/address snapshot | Downstream contract owner; Analytics mapping remains consent-gated | No purge while legal/operational retention remains open |
| `commerce.order.state.changed` | Internal operational; opaque Order ID, bounded states and version only | Idempotent in-app Notification; observability mapping pending / Order + Notification owners | No purge while legal/operational retention remains open |
| `catalog.projection.changed` | Public-catalog identity and bounded projection metadata | Search cache invalidation plus replay-safe provider-neutral Merchant refresh intent / Catalog + Search + Growth owners | No destructive purge job approved |
| `inventory.availability.changed` | Internal operational; opaque Variant/Warehouse IDs and version only | Replay-safe provider-neutral Merchant refresh intent; observability mapping pending / Inventory + Growth owners | No destructive purge job approved |
| `payment.verified` | Restricted financial metadata; opaque Payment ID and hashed operation identity only | Order confirmation/reconciliation / Payment + Commerce owners | Financial/legal retention; no destructive purge job approved |
| `quotation.revision.state.changed` | Internal commercial workflow metadata; opaque Quote ID, revision/version and bounded states only | Replay-safe Customer in-app Notification for Customer-owned Quotes; CRM/Analytics mapping pending / Quotation + Notification owners | Commercial/legal retention; no destructive purge job approved |
| `shipping.shipment.state.changed` | Internal operational; opaque Shipment ID, version and bounded states only | Replay-safe Customer in-app Notification resolved through authoritative Shipment→Order ownership; Analytics mapping pending / Shipping + Notification owners | Operational/legal retention; no destructive purge job approved |

No current payload permits raw email, phone, name, address, IP, user-agent, credentials, bank reference or provider body. Retention remains fail-closed: shortening retention or deleting dispatch evidence requires a separately approved operation and reconciliation proof.

The current `commerce.order.state.changed` consumer creates one Customer-scoped in-app Notification and one local delivery attempt per released fact. A relay replay validates the existing content and creates no duplicate. It does not invoke an email/SMS provider, and notification success or failure cannot change Order truth.

Quotation and Shipment state consumers follow the same replay-safe identity rule. Customer-owned Quotes are eligible, guest Quotes are deliberately skipped, and Shipment ownership is always rebuilt through its authoritative Order instead of trusting the event payload. The account projection exposes `subject_type` and `subject_public_id` for UI integration while retaining the existing Order fields; no view change is part of this backend slice.

Catalog and Inventory facts create one durable `merchant_feed_refresh_requests` intent per released fact. The consumer revalidates the executable fact contract and requires the referenced Catalog source to exist, including soft-deleted source rows needed for destination removal. It does not call a Merchant destination inside the relay boundary. The scheduled `merchant:process-refreshes` worker claims at most 25 eligible intents per run, expands approved brand/category/product/variant scopes from authoritative Catalog truth, coalesces identical variant revisions in that run, and records per-request/variant evidence in `merchant_feed_refresh_results`. Active sellable variants emit `upsert`; inactive or soft-deleted projections emit `remove`. Destination idempotency is stable by variant, operation and source revision. Failures back off and become `dead` after five attempts; the default destination fails visibly as `provider_unconfigured`.

## Common delivery envelope

- stable event identity supplied by the authoritative producer;
- exact fact type from the allow-list;
- UTC occurrence timestamp;
- public/opaque subject identifier, never an internal numeric database key;
- explicit consent decision before external delivery;
- bounded flat attributes with raw personal-data keys rejected;
- destination-specific idempotency and payload hash for replay/conflict detection.

The `analytics_event_intents` queue is the transaction-safe bridge for approved application producers. `analytics:process-intents` claims at most 25 eligible rows by default, recovers a 30-minute stale lease, resolves current consent immediately before delivery, retries provider failures with bounded exponential backoff and marks the fifth failure `dead`. Consent suppression completes without provider invocation. Its default destination is explicitly disabled.

## Reliability and privacy gates

- Persist the fact in the same transaction as the authoritative state. The implemented relay claims with `FOR UPDATE SKIP LOCKED` on MySQL, recovers expired leases, retries with bounded backoff and records terminal `dead` evidence.
- Retry with the same event identity; duplicate or out-of-order delivery must not duplicate an external effect.
- Provider outage must not roll back or block commerce state.
- Do not store raw provider request/response bodies, advertising profiles or a GA clone in the application database.
- Reject unknown fact types/versions/aggregates and every payload key or value outside the executable catalog before persistence.
- Retention eligibility is exposed only through the read-only `outbox:retention-status` report. Every fact class defaults to `UNAPPROVED`/zero eligible until D-006 Legal/Finance durations are supplied; no purge action exists.
- Operational response follows the [Transactional Outbox Runbook](../operations/outbox-runbook.md); destructive purge or state rewrite is not an incident shortcut.
- Legal/Finance retention durations and the uncovered `commerce.order.placed` consumer decision must be closed before Step 48 is marked `DONE`; schema/version, PII classification, ownership registry and dead-letter ownership now have executable evidence.

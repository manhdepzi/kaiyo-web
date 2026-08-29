# V1 Event Catalog Bootstrap

- Status: `IN PROGRESS — ORDER LIFECYCLE/CATALOG/INVENTORY/PAYMENT FACT/TRANSACTIONAL OUTBOX SLICE`
- Owner: Architecture + affected domain owner
- Rule: events are immutable facts, never commands; consumers must be idempotent and must not infer missing business data.

## Analytics delivery allow-list

| Fact name | Intended authoritative producer | Delivery state | Minimum subject |
| --- | --- | --- | --- |
| `catalog.product_viewed` | Public Catalog delivery after a successful public Product response | Producer pending | Product public identifier |
| `cart.item_added` | Cart action after commit | Producer pending | Cart/Variant public identifiers |
| `checkout.started` | Checkout application boundary after accepted validation | Producer pending | Customer/Cart public identifiers |
| `order.placed` | Checkout/Order after the authoritative transaction commits | Producer pending | Order public identifier |
| `quotation.requested` | Quotation after draft creation commits | Producer pending | Quotation public identifier |
| `crm.lead_created` | CRM after Lead creation commits | Producer pending | Lead public identifier |
| `contact.clicked` | Approved public contact action | Contract pending | Contact channel identifier |

The current Step 35 implementation validates and delivers this allow-list, but does not claim analytics producer completion. Analytics wiring still waits for explicit consent/attribution capture.

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

## Classification and ownership

| Fact | Data classification | Current consumer/owner | Retention state |
| --- | --- | --- | --- |
| `commerce.order.placed` | Internal operational; opaque commerce IDs, no customer/address snapshot | Downstream contract owner; Analytics mapping remains consent-gated | No purge while legal/operational retention remains open |
| `commerce.order.state.changed` | Internal operational; opaque Order ID, bounded states and version only | Idempotent in-app Notification; observability mapping pending / Order + Notification owners | No purge while legal/operational retention remains open |
| `catalog.projection.changed` | Public-catalog identity and bounded projection metadata | Search cache invalidation / Catalog + Search owners | No destructive purge job approved |
| `inventory.availability.changed` | Internal operational; opaque Variant/Warehouse IDs and version only | Merchant/observability mapping pending / Inventory owner | No destructive purge job approved |
| `payment.verified` | Restricted financial metadata; opaque Payment ID and hashed operation identity only | Order confirmation/reconciliation / Payment + Commerce owners | Financial/legal retention; no destructive purge job approved |
| `quotation.revision.state.changed` | Internal commercial workflow metadata; opaque Quote ID, revision/version and bounded states only | Notification/CRM/Analytics mapping pending / Quotation owner | Commercial/legal retention; no destructive purge job approved |
| `shipping.shipment.state.changed` | Internal operational; opaque Shipment ID, version and bounded states only | Notification/Analytics mapping pending / Shipping owner | Operational/legal retention; no destructive purge job approved |

No current payload permits raw email, phone, name, address, IP, user-agent, credentials, bank reference or provider body. Retention remains fail-closed: shortening retention or deleting dispatch evidence requires a separately approved operation and reconciliation proof.

The current `commerce.order.state.changed` consumer creates one Customer-scoped in-app Notification and one local delivery attempt per released fact. A relay replay validates the existing content and creates no duplicate. It does not invoke an email/SMS provider, and notification success or failure cannot change Order truth.

## Common delivery envelope

- stable event identity supplied by the authoritative producer;
- exact fact type from the allow-list;
- UTC occurrence timestamp;
- public/opaque subject identifier, never an internal numeric database key;
- explicit consent decision before external delivery;
- bounded flat attributes with raw personal-data keys rejected;
- destination-specific idempotency and payload hash for replay/conflict detection.

## Reliability and privacy gates

- Persist the fact in the same transaction as the authoritative state. The implemented relay claims with `FOR UPDATE SKIP LOCKED` on MySQL, recovers expired leases, retries with bounded backoff and records terminal `dead` evidence.
- Retry with the same event identity; duplicate or out-of-order delivery must not duplicate an external effect.
- Provider outage must not roll back or block commerce state.
- Do not store raw provider request/response bodies, advertising profiles or a GA clone in the application database.
- Operational response follows the [Transactional Outbox Runbook](../operations/outbox-runbook.md); destructive purge or state rewrite is not an incident shortcut.
- Schema/version, retention, PII classification, consumers and dead-letter ownership must be closed before Step 48 is marked `DONE`.

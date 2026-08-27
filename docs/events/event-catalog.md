# V1 Event Catalog Bootstrap

- Status: `IN PROGRESS — ORDER/CATALOG FACT/TRANSACTIONAL OUTBOX SLICE`
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
| `catalog.projection.changed` | `1` | Brand, Category, Product or Variant mutation | Aggregate type/public ID/version, change type and bounded changed-field references | Same-transaction `dispatch_records`; scheduled relay; idempotent Search cache invalidation consumer |

`commerce.order.placed` is an internal business fact and is not the external Analytics event name. An Analytics consumer may map it to `order.placed` only after it can prove the applicable consent revision and approved attribution data.

`catalog.projection.changed` contains public identifiers rather than numeric database keys. Attribute mutations lock and version their owning Product/Variant, so repeated changes cannot collapse into an unversioned projection fact. Search invalidation runs from the released fact and is therefore recoverable through the relay.

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

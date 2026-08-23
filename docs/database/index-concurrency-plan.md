# Step 07 — Index, Query, Concurrency and Idempotency Plan

## 1. Control

- Status: `APPROVED DESIGN — EXPLAIN/MIGRATION EVIDENCE PENDING EXECUTABLE SYSTEM`
- Companion schema: [V1 Physical Schema Dictionary](./schema-dictionary.md)
- Workload baseline: D006-A2 engineering profile and planning-horizon data volumes
- Approval date: 2026-08-23 (Asia/Bangkok)
- Boundary: defines access paths, index intent, transaction/lock order and durable idempotency; creates no migration and makes no production capacity claim

## 2. Integrity before performance

1. DB constraints enforce identity, FK, non-negative/reconciliation and unique-effect invariants even when application validation races.
2. Every invariant-changing read/write uses MySQL truth in one explicit transaction; Redis locks may reduce contention but cannot prove correctness.
3. Use InnoDB `READ COMMITTED` for application transactions once accepted by Step 09 ADR, with explicit `SELECT ... FOR UPDATE` or compare-and-swap updates. No correctness depends on implicit repeatable-read snapshots.
4. Lock the smallest approved rows in deterministic ascending primary-key order.
5. External/provider calls, notifications, search indexing and analytics never occur while a DB transaction is open.
6. Deadlock/lock-timeout retries are bounded to three attempts with jitter only for commands proven idempotent. Exhaustion returns/records a stable retryable failure; it never starts an unbounded loop.

## 3. Uniqueness and generated active keys

MySQL permits multiple `NULL` values in a unique index. For conditional active uniqueness, Step 11 migrations use a stored/generated nullable key with a named unique index; application-only checks are insufficient.

| Invariant | Approved physical strategy |
| --- | --- |
| Active CRM identity | Generated binary/string `active_identity_key = IF(active, normalized key material, NULL)`; unique index |
| Active Company membership | Generated key from company/account when status active; unique index |
| Active cart per owner | Separate generated customer/guest active key(s), each nullable and uniquely indexed |
| Active ownership assignment | Separate generated Customer/Company active key from explicit nullable FKs when `ends_at IS NULL`; each uniquely indexed |
| One active reconciliation case | Generated subject+reason key while non-terminal; unique index |
| One active cancellation request | Generated order key while requested; unique index |
| One shipment/order V1 | Direct unique `shipments.order_id` |
| One quote conversion/order | Unique `quote_conversions.quote_revision_id` and `order_id` |
| One balance/warehouse/variant | Unique `stock_balances(warehouse_id, variant_id)` |
| Provider event/effect | Unique provider code + normalized event/reference hash; never raw secret/reference in an index |

Generated expressions and byte lengths must be reviewed against MySQL index limits and the chosen collation before migration. Hash collision-sensitive identities retain normalized material or a secondary equality verification where required.

## 4. Critical access paths and indexes

Index column order follows equality predicates, then range/order, then covering IDs. Exact index names use `uq_`/`ix_` plus table/purpose and are documented in migrations.

### Identity and CRM

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Login by normalized email | unique `user_accounts(email_normalized)` including status lookup | one-row `const/ref`, no scan |
| Session authenticate/revoke | unique token hash; `(user_account_id, revoked_at, expires_at)` | one-row token lookup; bounded user revoke scan |
| Effective grants for actor | `(user_account_id, revoked_at, starts_at, ends_at)`, bundle/permission joins and explicit scope FKs | bounded grant set, no N+1 |
| Exact CRM duplicate | unique generated active identity key; `(subject_type, subject_id)` | one identity lookup |
| Sales assigned CRM list | active assignment `(owner_user_account_id, ends_at, customer_id/company_id)` plus team membership and subject status/update indexes | keyset page by `(updated_at,id)` |
| Lead queue | `(owner_user_account_id, status, created_at, id)` | server-filtered keyset page |
| Privacy task worker | `(status, created_at, id)` and `(privacy_request_id, domain_code)` unique | `SKIP LOCKED` bounded claims |

### Catalog, pricing and search sources

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Product by slug/SKU | unique reserved Product slug and Variant SKU | one-row lookup |
| Category listing | `(primary_category_id, status, id)`; Variant `(product_id,status,id)` | fixed query count with eager/batched relations |
| Category tree | `(parent_id, sort_order, id)` | bounded hierarchy query; cycle validation |
| Attribute filter source | `(attribute_definition_id, typed_value, product_id/variant_id)` according to approved filter | EXPLAIN for each approved filter; no JSON scan |
| Active price configuration | `(status, starts_at, ends_at)` plus target-scope indexes on rules | deterministic candidate set; ambiguity checked |
| Promotion eligibility/redemption | `(status, starts_at, ends_at, priority)`, eligibility dimension/target, unique redemption keys, `(promotion_id,customer_id,created_at)` | atomic limit checks with indexed count/ledger |
| Merchant changed items | source `(updated_at,id)` and revision indexes | resumable keyset batch |

### Inventory

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Lock availability | unique `(warehouse_id,variant_id)`; PK access after resolution | rows locked in ascending balance ID |
| Active reservation by source | unique `(source_type,source_public_id)`; `(status,expires_at,id)` | one source result; expiry worker keyset |
| Reservation items | unique `(inventory_reservation_id,stock_balance_id)` and `(stock_balance_id,status)` | bounded allocations |
| Movement ledger | unique `operation_key`; `(stock_balance_id,created_at,id)`; `(source_type,source_public_id)` | immutable audit page/keyset |
| Adjustment queue | `(status,created_at,id)`; `(warehouse_id,variant_id,status)` | bounded approval/operation page |

### Quote, Cart and Order

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Current quote/revisions | unique `(quote_id,revision_no)`; Quote current FK; `(state,valid_until,id)` | one aggregate load; expiry worker indexed |
| Quote customer/staff list | `(customer_id,created_at,id)`, `(company_id,created_at,id)` and ownership-scoped query path | keyset pagination; authorization predicate in SQL |
| Quote conversion | unique revision/order/conversion key | exactly-one result under concurrency |
| Cart lookup/merge | generated active owner keys; unique `(cart_id,variant_id)` | deterministic aggregate load |
| Order lookup/list | public ID unique; `(customer_id,created_at,id)`, `(company_id,created_at,id)`, `(state,created_at,id)` | own/scope-filtered keyset pages |
| Fulfillment queue | `(state,updated_at,id)` | bounded worker/admin page |
| Order lines/history | unique `(order_id,line_no)`; history `(order_id,occurred_at,id)` | fixed aggregate queries |
| Cancellation queue | generated active order key; `(state,created_at,id)` | one active request and bounded page |

### Payment, tax, invoice and shipping

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Payment/order and attempts | `(order_id,state,id)`; unique `(payment_id,attempt_no)` | bounded aggregate load |
| Provider webhook dedupe | unique `(provider_code,event_identity_hash)` before applying | duplicate rejected/returns stored result |
| Provider transaction reconcile | unique provider reference hash; `(state,created_at,id)` for unknown cases | one fact lookup, bounded reconciliation queue |
| Refund eligibility/result | `(payment_id,state,id)`, unique cancellation/payment eligible scope | one full refund effect |
| Tax config/classification | active effective index; unique revision+classification code | deterministic snapshot calculation |
| Invoice queue/reference | unique order active lineage; `(state,created_at,id)`; provider ref unique when present | Finance queue/keyset and dedupe |
| Shipment/order/tracking | unique order ID; provider tracking/event unique; `(state,updated_at,id)` | one V1 shipment; bounded tracking worker |

### CMS, jobs and delivery evidence

| Query/command | Required index/constraint | Target evidence |
| --- | --- | --- |
| Public content by slug | unique reserved type slug/code and current published revision FK | one root+revision query |
| Scheduled publication | `(state,due_at,id)` plus unique operation key | `SKIP LOCKED` bounded claims |
| Notification worker | unique idempotency key; `(state,next_attempt_at,id)` | bounded at-least-once claims |
| Dispatch relay | unique fact identity; `(state,next_attempt_at,id)` | committed records relayed with `SKIP LOCKED` |
| Idempotency command | unique `(scope,key_hash)` | one durable claim/outcome |
| Audit lookup | `(target_type,target_public_id,occurred_at,id)`, `(actor_user_account_id,occurred_at,id)`, `(correlation_id,id)` | bounded investigations; no unindexed JSON predicate |

## 5. Pagination and query-shape policy

- Public/admin lists use keyset pagination by a stable tuple such as `(created_at,id)` or `(sort_key,id)`. Offset pagination is allowed only for small approved configuration lists and never for high-volume tables.
- Every list query applies authorization/resource scope in SQL before pagination; filtering in PHP after retrieval is forbidden.
- Controllers/resources do not lazy-load. Application queries declare eager/batch relationships and tests assert query count for representative page size.
- Counts/KPIs use dedicated aggregate queries/read models; no per-row subquery/N+1 dashboard construction.
- Search results return source public IDs and are revalidated against authoritative visibility/sellability before invariant-sensitive display/action.
- All production-like EXPLAIN evidence is collected after migrations/fixtures exist; Step 07 approval is index intent, not a false runtime PASS.

## 6. Transaction and lock plans

### 6.1 Generic idempotent command

1. Normalize command and compute request hash; never include secrets.
2. Insert or lock `idempotency_outcomes(scope,key_hash)`.
3. If terminal and request hash matches, return stored authoritative result; if hash differs, reject conflict.
4. If safely claimable, set processing owner/lease inside transaction.
5. Lock the aggregate root by PK/unique key, validate expected `lock_version`, execute transition and append evidence/dispatch record.
6. Store terminal outcome in the same authoritative transaction when possible.
7. Commit; only then run external side effects through relay/queue.

### 6.2 Checkout/order placement

Lock order is fixed:

1. idempotency outcome;
2. Cart root and lines (if cart-based);
3. active Pricing/Tax configuration revisions needed for deterministic snapshot (read consistently; immutable active revisions avoid long locks);
4. Stock Balance rows sorted ascending by balance PK with `FOR UPDATE`;
5. insert Order/Lines/Address/Tax snapshot, Inventory Reservation/Items/Movements and Dispatch Records;
6. terminal idempotency outcome; commit.

Provider intent creation is post-commit. If an approved provider requires pre-created server state, its adapter contract must use a safe compensating/reconciliation design; no network call is inserted into this transaction by convenience.

### 6.3 Reservation release/commit/expiry

1. Lock reservation root by unique source/public ID.
2. Return existing terminal result for duplicate operation.
3. Lock referenced Stock Balances ascending by PK.
4. Revalidate state/timing/evidence; update quantities, reservation items/root and append movements.
5. Insert dispatch/audit/outcome; commit.

An expiry worker claims due active reservations in small keyset batches using `FOR UPDATE SKIP LOCKED`; authoritative time comes from DB/application UTC clock policy. It cannot expire after verified confirmation as defined by D-002/D-004.

### 6.4 Quote approval/send/accept/convert

- Approval/send: lock Quote then target Revision; verify integrity hash/current revision and D-003/D-005 authority; append approval and transition to Sent. Notification is after commit.
- Accept: lock Quote/Revision; verify Sent/Viewed, validity and expected version; transition only to Accepted.
- Convert: claim conversion idempotency, lock Accepted Revision, then lock Stock Balances ascending and execute Order/Reservation creation. Unique Quote Conversion is the final exactly-once guard.

### 6.5 Payment webhook/refund

- Persist/deduplicate provider event identity before effect. Invalid signature records security evidence but never applies a financial transition.
- Lock Payment then Attempt by PK; apply only a valid monotonic transition. An unknown/out-of-order result creates/updates Reconciliation Case without guessing.
- Payment commits its own fact/dispatch record. Commerce consumes verified fact idempotently in a separate transaction; there is no distributed transaction or reverse table mutation.
- Refund locks Refund/Payment in stable PK order, validates full eligible amount/CANCEL-D1 and invokes provider only through a durable retry/reconciliation operation. Duplicate provider callbacks map to the same refund outcome.

### 6.6 Shipment dispatch/tracking

- Dispatch locks Shipment and its Items; append verified dispatch evidence/dispatch record. Inventory commit and Order advancement are idempotent consumer transactions in owning domains.
- Tracking events dedupe by provider event identity. Only verified, mapped monotonic evidence updates current shipment state.
- Correction appends a separate row while pre-Delivered; original event remains unchanged.

### 6.7 Configuration activation

Price, promotion, tax, shipping fee and template activation locks the configuration lineage/revision, validates non-overlap/ambiguity, activates exactly one revision and supersedes prior revision atomically. Historical document snapshots are never updated.

## 7. Constraint catalogue

| Invariant | DB protection plus application proof |
| --- | --- |
| Available stock never negative | `on_hand_qty ≥ 0`, `reserved_qty ≥ 0`, `reserved_qty ≤ on_hand_qty`; locked arithmetic update and concurrency tests |
| Quote revision/order line totals immutable/reconcile | non-negative checks, VND currency checks, application value objects and hash/snapshot tests; DB generated totals considered only if exact rounding semantics match |
| One order per accepted quote revision | unique Quote Conversion revision/order plus idempotency outcome |
| Duplicate webhook safe | provider event unique index before transition plus stored processing result |
| No over-authority/self approval | D-005 policy check, proposer/approver inequality check where representable, immutable approval evidence |
| No ambiguous active configuration | interval/priority activation transaction, generated active key where possible and deterministic validation query |
| No negative/fraction misuse | quantity >0 checks and Variant quantity-scale validation |
| Historical evidence not rewritten | no `updated_at` on append-only tables, repository/API excludes update/delete and security tests prohibit direct mutation |
| AI absent from V1 | no AI table/FK/migration/package namespace; architecture dependency test at Step 11 |

MySQL `CHECK` does not replace domain transition validation, and application validation does not replace enforceable DB uniqueness/check/FK constraints.

## 8. Soft-delete and retention policy

| Class | Policy |
| --- | --- |
| Accounts/CRM | disable/anonymize; `deleted_at` only after PRIV-D1 task proves retention eligibility; normalized active identity deactivated deliberately |
| Catalog/CMS/config | deactivate/unpublish/supersede; slug/SKU/code remains reserved to prevent historical ambiguity |
| Cart/operational projection | purge only after approved retention and no authoritative reference; derived stores rebuildable |
| Quote/Order/Payment/Refund/Tax/Invoice/Shipment/Inventory/Audit/Approval | no hard delete; retention/legal policy controls archival/anonymization, never referential destruction |
| Provider payload | store hash/redacted minimal evidence; raw payload only if approved encrypted retention/access contract exists |

No scheduled deletion job is implemented while D-006 legal retention/residency is open.

## 9. Verification plan before migration approval

- Generate migration-to-dictionary trace: every table/column/index/FK/check maps to this revision.
- Run schema-lint and MySQL 8.4 migration on empty DB and upgrade fixture.
- Seed D-006 planning-horizon representative distributions without production PII.
- Capture `EXPLAIN ANALYZE` for every critical access path; reject full scans/filesort/temp tables unless measured, bounded and explicitly accepted.
- Run concurrency suites: duplicate CRM identity, price ambiguity, parallel stock reserve/release/commit, double checkout, quote conversion, duplicate/out-of-order payment event/refund, schedule/dispatch claims.
- Assert query counts for public/catalog/cart/portal/admin critical pages.
- Prove Redis/search loss does not lose truth or authorize a write.
- Prove all V1 migrations contain no AI entities.

## 10. Step 07 approval

| Check | Result |
| --- | --- |
| Critical access paths mapped to indexes | `PASS — DESIGN` |
| Integrity constraints and active uniqueness defined | `PASS — DESIGN` |
| Lock ordering/concurrency/idempotency defined | `PASS — DESIGN` |
| Soft-delete/retention and V2 exclusions defined | `PASS` |
| Runtime EXPLAIN/load/concurrency evidence | `PENDING EXECUTABLE SYSTEM — CORRECTLY NOT CLAIMED` |
| Migration created | `NO — CORRECT FOR STEP 07` |

Step 07 design is complete. Step 08 may define approved HTTP contract conventions and OpenAPI skeleton; Step 09 must accept the transaction isolation/reliable-dispatch decisions before Step 11 migrations.

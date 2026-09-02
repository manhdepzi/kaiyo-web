# Step 07 — V1 Physical Schema Dictionary

## 1. Control

- Status: `APPROVED DESIGN — STEPS 12–26 IMPLEMENTED; STEPS 27–28 DELIVERY CONTRACTS DONE; STEP 29 READY`
- Database baseline: MySQL 8.4 LTS, InnoDB, `utf8mb4` with one approved accent/case collation chosen consistently at scaffold time
- Approval date: 2026-08-23 (Asia/Bangkok)
- Inputs: approved Step 03 rules, Step 05 boundaries and [Step 06 ERD](../architecture/v1-erd.md)
- Companion: [Index, Query, Concurrency and Idempotency Plan](./index-concurrency-plan.md)
- Boundary: this dictionary approves physical table/column/constraint intent; Steps 12–16 reproduce the identity/authorization/CRM/Catalog/Pricing subset, while later domain migrations remain gated by their owning implementation steps

## 2. Global conventions

| Concern | Approved convention |
| --- | --- |
| Internal primary key | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| Public identity | `public_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`, application-generated monotonic ULID, `UNIQUE`; never expose sequential `id` in public contracts |
| Time | `DATETIME(6)` stored in UTC; names end `_at`; effective ranges use `[starts_at, ends_at)` with nullable `ends_at` |
| Optimistic concurrency | Mutable aggregate roots carry `lock_version BIGINT UNSIGNED NOT NULL DEFAULT 0`; every guarded update increments it |
| Money | V1 VND amount: `DECIMAL(20,0)` plus `currency CHAR(3)` constrained to `VND`; never floating point |
| Quantity | `DECIMAL(20,4)` to support measured products without schema replacement; business configuration may restrict a Variant to whole units |
| Percentage/rate | Discount `DECIMAL(7,4)` percentage points; tax rate `DECIMAL(9,6)`; explicit range checks |
| State | Readable `VARCHAR(32)` with named `CHECK` constraint matching approved vocabulary; transitions remain application-domain rules plus expected-version guards |
| Boolean | `BOOLEAN NOT NULL` (MySQL `TINYINT(1)`) with explicit default only where the business default is approved |
| Text/JSON | JSON only for provider evidence, redacted audit metadata or type-specific non-query payload; no core price/stock/permission/filter field is JSON-only |
| Sensitive values | Normalized lookup values stored separately from display/encrypted values where required; passwords/tokens store one-way hashes only; provider secrets never stored in domain tables |
| Foreign keys | Named FKs; `RESTRICT` by default. `CASCADE` only for purely owned non-audit draft children explicitly listed in a migration review |
| Deletes | Financial, inventory, approval, audit and issued document facts are never hard-deleted. Config/catalog/CRM roots use status and optional `deleted_at`; unique identities remain reserved |
| Audit columns | Mutable roots include `created_at`, `updated_at`, and actor/source reference where needed. Append-only evidence uses `created_at` and no `updated_at` |
| Correlation | Critical commands/evidence carry `correlation_id CHAR(26) ascii_bin`; retryable commands also reference a durable idempotency outcome |

Every nullable field requires semantic meaning in its table definition; null must not mean multiple undocumented states.

## 3. Identity and authorization

| Table | Essential columns beyond common identity/time | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `user_accounts` | `email_display VARCHAR(320)`, `email_normalized VARCHAR(320)`, `password_hash VARCHAR(255)`, `status VARCHAR(24)`, `email_verified_at`, encrypted nullable `two_factor_secret`/`two_factor_recovery_codes`, `two_factor_confirmed_at`, `two_factor_enabled_at`, `disabled_at`, `remember_token`, `lock_version` | unique `email_normalized`; status check `pending/active/disabled`; password hash required for local login; 2FA enabled timestamp requires confirmed encrypted secret/recovery state | status-disable; `deleted_at` only after approved privacy/retention action |
| `auth_sessions` | `user_account_id`, `token_hash BINARY(32)`, `last_seen_at`, `expires_at`, `revoked_at`, `ip_hash BINARY(32) NULL`, `user_agent_redacted VARCHAR(512) NULL` | FK user; unique token hash; expiry after creation | purge after approved session/security retention |
| `password_reset_tokens` | `email VARCHAR(320)` containing the normalized identity, `token VARCHAR(255)`, `created_at` | primary/unique normalized email; framework-compatible column name; token is one-way hashed by the password broker | purge on use/expiry; no plaintext token retained |
| `authentication_events` | `user_account_id NULL`, `event_type VARCHAR(32)`, `email_hash BINARY(32) NULL`, `session_token_hash BINARY(32) NULL`, `ip_hash BINARY(32) NULL`, `user_agent_redacted VARCHAR(512) NULL`, `occurred_at`, `correlation_id` | event type check `login_succeeded/login_failed/logout/session_revoked/account_disabled/password_reset/2fa_changed`; append-only and privacy-minimized | security retention remains D-006/legal configuration gated |
| `role_bundles` | `public_id`, `code VARCHAR(100)`, `name VARCHAR(160)`, `status`, `requires_two_factor`, `lock_version` | unique code; active/inactive | no key reuse; deactivate |
| `permission_definitions` | `code VARCHAR(160)`, `module VARCHAR(32)`, `description VARCHAR(500)`, `impact VARCHAR(16)`, `status` | unique code; impact check `normal/high`; active/inactive | no key reuse; deactivate |
| `permission_scope_types` | `permission_definition_id`, `scope_type VARCHAR(32)` | unique permission+scope; allowed scope vocabulary check | explicit remove only through approved catalog migration |
| `role_permissions` | `role_bundle_id`, `permission_definition_id` | unique pair; both RESTRICT | explicit remove with audit |
| `scoped_grants` | `public_id`, `user_account_id`, `role_bundle_id NULL`, `permission_definition_id NULL`, `scope_type VARCHAR(32)`, `module_code NULL`, nullable explicit `customer_id/company_id/sales_team_id/warehouse_id`, `starts_at`, `ends_at`, status, grant/approve/revoke actor IDs, `reason`, identity/active hashes, `lock_version` | exactly one of bundle/permission; scope type requires its matching target; high-impact distinct approver; valid interval; one active identity | never hard-delete; revoke/expire |
| `break_glass_authorizations` | `public_id`, requester/approver IDs, exact `permission_definition_id`, exact scope columns, `reason VARCHAR(1000)`, `starts_at`, `expires_at`, `status`, `reviewed_at`, `reviewed_by_user_account_id NULL`, `review_notes` | requester ≠ approver; duration ≤60 minutes; exact high-impact permission/scope; state check `requested/approved/rejected/expired/reviewed/revoked` | append/terminal retention with security audit |
| `authorization_events` | actor/subject account IDs, `event_type`, target type/public ID, before/after hashes, reason, `occurred_at`, correlation ID | append-only event type catalog; no credential or unrestricted payload | security retention remains D-006 gated |

Resource target FK activation is staged: Step 13 creates typed nullable scope columns and rejects unverified resource-target grants; Steps 14/17 and the owning Sales Team/Warehouse migrations add the explicit FKs before those scope types can be issued. This approved staging avoids premature placeholder domain tables.

## 4. CRM

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `customers` | `public_id`, `user_account_id NULL`, `display_name`, indexed `name_normalized`, `status`, `primary_email_display NULL`, `primary_email_normalized NULL`, `primary_phone_display NULL`, `primary_phone_e164 NULL`, `lock_version`, `deleted_at NULL` | unique non-null user account; normalized contact uniqueness implemented through active identity table below | anonymize/status; commerce refs RESTRICT |
| `customer_addresses` | `public_id`, `customer_id`, label, recipient/company/tax, address/locality/subdivision/postal/country/phone fields, shipping/billing defaults, `status`, `lock_version`, `deleted_at NULL` | MySQL generated unique keys enforce one active shipping and one active billing default per Customer; country/status/deactivation checks; portal access index | Customer may deactivate; Checkout copies submitted values into immutable Order snapshots; maximum 20 active rows enforced by application action |
| `customer_wishlist_items` | `customer_id`, `product_id`, timestamps | unique Customer+Product; Customer portal ordering index; application accepts only active public Product and limits each Customer to 100 | Customer/Product deletion cascades derived preference only; inactive Product is hidden from delivery |
| `companies` | `public_id`, `legal_name`, `display_name`, indexed `name_normalized`, `tax_code_display NULL`, `tax_code_normalized NULL`, `status`, `lock_version`, `deleted_at NULL` | unique non-null normalized verified tax identity through identity table | anonymize/status; no destructive commerce break |
| `crm_identity_keys` | `subject_type VARCHAR(16)`, `subject_id`, `key_type VARCHAR(24)`, `normalized_hash BINARY(32)`, `verified_at NULL`, `active BOOLEAN` | unique active logical `(key_type, normalized_hash)` enforced by generated active key strategy in companion plan; type check tax/email/phone | deactivate; retain merge lineage according to privacy policy |
| `company_memberships` | `company_id`, `user_account_id`, `status`, `starts_at`, `ends_at`, `lock_version` | unique active membership; interval valid | expire/revoke, no hard delete while evidence referenced |
| `company_member_capabilities` | `company_membership_id`, `permission_definition_id`, `granted_by_user_account_id`, `revoked_at NULL` | unique active membership+permission; FK maps the approved permission catalog | revoke |
| `sales_teams` | `public_id`, `name`, `manager_user_account_id`, `status`, `lock_version` | manager must hold eligible active Manager authority; active/inactive | deactivate |
| `sales_team_memberships` | `sales_team_id`, `user_account_id`, `starts_at`, `ends_at NULL`, `assigned_by_user_account_id` | unique active team+account; interval valid | append interval history |
| `contacts` | `customer_id NULL`, `company_id NULL`, `name`, normalized/display email/phone, `status`, `lock_version`, `deleted_at NULL` | exactly one owner; identity keys handle exact blocking; no ambiguous dual commercial ownership | anonymize/status |
| `leads` | `source VARCHAR(64)`, identity/display contact values, `status`, `owner_user_account_id NULL`, `converted_customer_id NULL`, `converted_company_id NULL`, `converted_at NULL`, `lock_version` | conversion targets only when converted; state check `new/qualified/disqualified/converted` | status/anonymize; attribution retained as approved |
| `ownership_assignments` | `customer_id NULL`, `company_id NULL`, `owner_user_account_id`, `sales_team_id NULL`, `starts_at`, `ends_at NULL`, `assigned_by_user_account_id`, `reason` | exactly one Customer/Company FK; no overlapping active owner for same subject unless future policy approves co-ownership | append interval history |
| `duplicate_reviews` | nullable candidate/target Customer/Company FKs, `match_kind`, `evidence_redacted JSON`, `status`, reviewer/decision/reason, `lock_version` | exactly one candidate and one target subject; normalized pair unique while open; state check | retain decision evidence; no PII-rich raw payload |
| `crm_merge_records` | source/target subject type/IDs, `plan_hash BINARY(32)`, proposer/approver, `executed_at`, `correlation_id` | unique source+target+plan hash; source cannot equal target | immutable |
| `privacy_requests` | `customer_id NULL`, `company_id NULL`, `requester_user_account_id NULL`, `request_type`, `legal_basis_reference`, `status`, `approved_by_user_account_id NULL`, `lock_version` | exactly one subject FK; state check `received/verified/reviewing/blocked/executing/completed/rejected`; unresolved retention => blocked | immutable request/result evidence without erased PII |
| `privacy_request_tasks` | `privacy_request_id`, `domain_code`, `status`, `result_counts JSON NULL`, `error_code NULL`, `attempts` | unique request+domain; resumable state check | retained with request |

## 5. Media, Catalog and Pricing

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `media_assets` | `public_id`, `storage_key VARCHAR(1024)`, `original_name`, `declared_mime`, `detected_mime`, `byte_size BIGINT UNSIGNED`, `sha256 BINARY(32)`, `access_class`, `scan_status`, `status`, `lock_version`, `deleted_at NULL` | unique storage key; size >0; access `public/private`; scan state constrained | quarantine/status; object delete only after usage/recovery checks |
| `media_variants` | `media_asset_id`, `variant_code`, `storage_key`, dimensions/byte size/mime | unique asset+variant code and storage key | owned cleanup after reference checks |
| `categories` | `parent_id NULL`, `name`, `slug`, `status`, `sort_order`, `lock_version`, `deleted_at NULL` | unique reserved slug; parent ≠ self; cycle prevented application + recursive validation | deactivate; slug reserved |
| `brands` | `name`, `slug`, `status`, `lock_version`, `deleted_at NULL` | unique reserved slug | deactivate |
| `products` | `brand_id NULL`, `primary_category_id`, `name`, `slug`, `status`, `description`, `detailed_description LONGTEXT NULL`, `seo_title VARCHAR(70) NULL`, `seo_description VARCHAR(180) NULL`, `lock_version`, `deleted_at NULL` | unique reserved slug; status `draft/active/inactive`; bounded SEO fields | deactivate; issued snapshots unaffected |
| `variants` | `product_id`, `sku VARCHAR(100)`, `name`, `quantity_scale TINYINT UNSIGNED`, `status`, `lock_version`, `deleted_at NULL` | unique reserved SKU; scale 0..4 | deactivate; no SKU reuse |
| `attribute_definitions` | `code`, `name`, `value_type`, `filterable`, `status` | unique code; approved types | deactivate |
| `product_attribute_values` | `attribute_definition_id`, `product_id NULL`, `variant_id NULL`, typed value columns | exactly one owner and exactly one typed value; unique owner+definition | explicit replace with audit for mutable catalog |
| `catalog_media_references` | `product_id NULL`, `variant_id NULL`, `media_asset_id`, `purpose`, `sort_order` | exactly one catalog owner; purpose `primary/gallery/document/video`; unique owner+asset+purpose | explicit detach; media ownership retained |
| `product_reviews` | `public_id`, `customer_id`, `product_id`, `verified_order_id`, `rating`, `title`, `body`, `status`, moderator/reason/timestamps, `lock_version` | unique Customer+Product; rating 1..5; verified Order evidence; moderation-state consistency; public and moderation indexes | pending/rejected may be versioned; approved Customer content is immutable; retain moderation and purchase evidence |
| `slug_redirects` | `source_path VARCHAR(2048)`, `target_path VARCHAR(2048)`, `owner_type`, `owner_id`, `status_code SMALLINT`, `active` | unique active source; source ≠ target; status code approved 301/308 | retain redirect lineage; deactivate only with SEO review |
| `price_configurations` | `public_id`, `revision_no`, `status`, `starts_at`, `ends_at NULL`, proposer/approver IDs, `lock_version` | unique public lineage+revision; interval valid; status draft/active/superseded | immutable after activation |
| `price_rules` | `price_configuration_id`, `layer`, target scope identifiers, `priority`, `amount`, `currency`, `minimum_quantity`, effective interval | valid D-001 layer; amount ≥0; VND; ambiguity prevented by query/activation validation | immutable under activated revision |
| `promotions` | `public_id`, `revision_no`, `type`, `value`, `priority`, effective interval, usage/per-customer limits NULL, `status`, proposer/approver | type percent/fixed; percent 0..100; value positive; interval valid | immutable activated revision; supersede |
| `promotion_eligibilities` | `promotion_id`, dimension/type/target reference, minimum subtotal/quantity NULL | valid dimension; non-negative minima | owned by promotion revision |
| `promotion_redemptions` | `promotion_id`, `customer_id NULL`, `company_id NULL`, `order_id`, `redemption_key CHAR(26)`, `amount`, `created_at` | unique redemption key and promotion+order; amount non-negative | immutable financial evidence |

## 6. Inventory

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `warehouses` | `code`, `name`, `status`, `lock_version` | unique code | deactivate |
| `stock_balances` | `warehouse_id`, `variant_id`, `on_hand_qty`, `reserved_qty`, `lock_version` | unique warehouse+variant; quantities ≥0; reserved ≤ on-hand for no-backorder V1 | never delete while movement/reference exists |
| `stock_movements` | `stock_balance_id`, `movement_type`, `quantity_delta`, `source_type`, `source_public_id`, `operation_key`, actor/correlation | delta non-zero; unique operation key; append-only | immutable |
| `inventory_reservations` | `public_id`, `source_type`, `source_public_id`, `status`, `expires_at NULL`, `committed_at NULL`, `released_at NULL`, `lock_version` | unique source identity; states active/released/committed/expired; terminal timestamps consistent | immutable terminal evidence |
| `reservation_items` | `inventory_reservation_id`, `stock_balance_id`, `quantity`, `status`, `lock_version` | unique reservation+balance; quantity >0 | retained with reservation |
| `inventory_adjustments` | `public_id`, `warehouse_id`, `variant_id`, `quantity_delta`, `reason`, `evidence_media_asset_id NULL`, proposer/approver IDs, `status`, `lock_version` | delta non-zero; proposer ≠ approver; approved before execution | immutable decision/movement link |

## 7. Quotation, Cart and Order

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `quotes` | `public_id`, `customer_id`, `company_id NULL`, `current_revision_id NULL`, `lock_version` | Customer required; current revision belongs quote | retain; no hard delete after submission |
| `quote_revisions` | `quote_id`, `revision_no`, `state`, `currency`, subtotal/discount/VAT/shipping/final totals, `valid_until NULL`, `sent_at/viewed_at/accepted_at/rejected_at/expired_at/converted_at NULL`, `integrity_hash BINARY(32)`, `lock_version` | unique quote+revision; VND; totals ≥0/reconcile; state/timestamp consistency; Sent+ immutable | immutable after Sent; prior revisions retained |
| `quote_lines` | `quote_revision_id`, line no, variant/SKU/name snapshot, quantity, base/final unit amount, discount/tax/line totals, pricing/config references | unique revision+line no; qty >0; amounts ≥0; line reconciliation | immutable with Sent revision |
| `quote_approvals` | `quote_revision_id`, trigger/tier, proposer/approver IDs, decision, reason, proposal hash, decided_at | unique revision+tier/decision identity; proposer ≠ approver when over-limit | immutable |
| `quote_access_events` | `quote_revision_id`, access_kind, actor/customer evidence hash, occurred_at, event_key | unique event key; informational only | append-only, privacy-minimized |
| `quote_conversions` | `quote_revision_id`, `order_id`, `conversion_key`, `converted_at` | unique quote revision, unique order, unique key | immutable |
| `carts` | `public_id`, `customer_id NULL`, `guest_token_hash BINARY(32) NULL`, `status`, `lock_version`, `expires_at NULL` | exactly one owner identity; one active cart per owner through active-key strategy | purge after approved cart retention when not referenced |
| `cart_lines` | `cart_id`, `variant_id`, `quantity`, advisory pricing revision/result NULL, `lock_version` | unique cart+variant; qty >0 | owned by cart; no financial history role |
| `orders` | `public_id`, `cart_id`, `customer_id`, `company_id NULL`, `inventory_reservation_id`, `state`, `currency`, merchandise/discount/VAT/shipping/final totals, payment/shipping/tax preparation snapshots, `invoice_requested`, `placed_at`, `lock_version` | unique Cart and Reservation; VND; totals reconcile; Step 21 permits only initial Pending state; Step 22 adds ORD-L1 transitions | never hard-delete |
| `order_lines` | `order_id`, `variant_id`, `pricing_snapshot_id`, SKU/name snapshot, quantity, unit/line amounts and pricing resolution | unique order+variant; qty >0; VND; MySQL update/delete triggers | immutable |
| `order_address_snapshots` | `order_id`, `address_type`, recipient/company/tax/address/contact snapshot, country/subdivision/postal fields | unique order+address type; billing/shipping type check | immutable/minimized under legal retention |
| `order_status_history` | `order_id`, from/to state, actor/evidence, reason, occurred_at, correlation_id | exact ORD-L1/CANCEL-D1 transition-pair CHECK; update/delete triggers | immutable |
| `checkout_operations` | case-sensitive operation key, request hash, `cart_id`, result `order_id`, created_at | unique key, Cart and result Order; conflicting request reuse rejected | retained as idempotency evidence |
| `order_transition_operations` | case-sensitive operation key, request hash, Order, result state/version, created_at | unique operation; result binds exact evidence payload | immutable by trigger |
| `cancellation_requests` | `public_id`, `order_id`, requester, reason, state, decider/reason, decision time, `lock_version` | decision states requested/approved/denied/completed; unique active request per order via active key | immutable |

## 8. Payment, Tax, Invoice and Shipping

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `payments` | `public_id`, `order_id`, method, payable amount/currency, state, `lock_version` | VND; amount = approved payable scope; states pending/processing/paid/failed/unknown | never hard-delete |
| `payment_attempts` | `payment_id`, attempt no, provider_code NULL, provider_intent_ref_hash NULL, state, expires_at NULL, `lock_version` | unique payment+attempt; provider ref unique when present; method TTL consistent | immutable terminal history |
| `payment_transactions` | `payment_attempt_id`, type, amount/currency, verified_at, provider_transaction_ref_hash, operation_key | unique provider ref and operation key; amount >0; types charge/void/refund evidence as approved | immutable |
| `payment_provider_events` | provider code/event identity hash, received/verified times, signature result, event type, payload hash/redacted payload, processing state | unique provider+event identity; unverified never applied | immutable security/financial evidence |
| `refunds` | `public_id`, `payment_id`, `cancellation_request_id`, full amount/currency, state, provider ref hash NULL, `lock_version` | V1 amount must equal eligible paid amount; unique cancellation/payment refund scope | never hard-delete |
| `reconciliation_cases` | subject type/ID, reason code, state, owner user NULL, resolution evidence, `lock_version` | one active case per subject+reason via active key | immutable resolution history |
| `tax_configuration_revisions` | revision, effective interval, status, proposer/approver | no ambiguous active interval | immutable after activation |
| `tax_classifications` | config revision, code, rate, applicability attributes | unique revision+code; rate range approved; no hard-coded default | retained with config |
| `tax_snapshots` | `quote_revision_id NULL`, `order_id NULL`, configuration/classification refs, taxable base/rate/amount/currency, state, integrity hash | exactly one owner FK; owner unique; VND; amounts ≥0; finalized immutable | never rewrite finalized |
| `invoices` | `public_id`, `order_id`, request snapshot, eligibility evidence, issuer/provider ref NULL, state, issued_at NULL, `lock_version` | one active invoice lineage per order; state requested/eligible/issued/cancelled/corrected | legal retention; never hard-delete |
| `invoice_corrections` | invoice ID, correction type, reason, amount refs, approver, provider ref NULL, issued_at | append-only; original invoice unchanged | legal retention |
| `shipping_methods` | code/name, method type, status, configuration revision | unique code | deactivate |
| `shipping_fee_configurations` | method ID, revision, scope, fee/free threshold NULL, VND, effective interval, status | no ambiguous active scope interval | immutable activated revision |
| `shipments` | `public_id`, `order_id`, method ID NULL, carrier code/ref hash NULL, state, booked/packed/dispatched/delivered times, `lock_version` | unique order in V1; approved states; one shipment/order | never hard-delete |
| `shipment_items` | shipment ID, order_line ID, quantity | unique shipment+order line; qty >0 and ≤ order qty | retained |
| `carrier_events` | shipment ID, provider/event identity, mapped state, occurred/received times, signature result, payload hash/redacted payload, processing state | unique provider+event identity; only verified event advances state | append-only by trigger |
| `shipment_operations` | case-sensitive operation key, request hash, Shipment, action/result state/version, redacted evidence, created_at | unique operation; conflicting reuse rejected; exact result retained | append-only by trigger |
| `tracking_corrections` | tracking event ID, corrected mapped state/evidence, reason, operations actor, approved_at | original unchanged; pre-Delivered only; unique correction command | append-only |

## 9. CMS, Notification and derived delivery evidence

| Table | Essential columns | FK / uniqueness / checks | Delete/retention |
| --- | --- | --- | --- |
| `pages`, `articles`, `faqs`, `banners` | separate type-specific roots: public ID, slug/code, status, current revision ID, lock version, deleted_at NULL | reserved slug/code; status draft/published/unpublished | unpublish/deactivate; published lineage retained |
| corresponding `*_revisions` | root ID, revision no, type-specific content, integrity hash, created by/time | unique root+revision; published revision immutable | retained |
| `publication_schedules` | nullable explicit Page/Article/FAQ/Banner root+revision FKs, action, due time, state, operation key | exactly one content/revision owner; unique operation key; idempotent publish/unpublish | retained evidence |
| `content_media_references` | nullable explicit Page/Article/FAQ/Banner revision FKs, media asset ID, purpose/sort | exactly one revision owner; unique owner revision+asset+purpose | explicit detach on draft only |
| `email_templates` | code, status, current revision ID, lock version | unique reserved code | deactivate |
| `email_template_revisions` | template ID, revision, subject/body, allowed variables JSON, integrity hash, published at | unique template+revision; published immutable; no executable code | retained |
| `notifications` | public ID, Customer FK, nullable Order/Quote/Shipment FKs, channel, template key, business fact reference, bounded attributes, state, idempotency hash, sent/read times | unique idempotency hash; state queued/sending/sent/failed/dead; current in-app producers contain no copied destination/PII; workflow subject ownership is rebuilt from domain truth | operational/privacy retention; V1 in-app subset and workflow subjects physically implemented by migrations `000020`/`000027` |
| `notification_attempts` | notification ID, attempt no, provider code, outcome/error/latency, attempted at | unique notification+attempt; no secret/full sensitive payload; current provider is local `in_app` only | operational retention; V1 in-app subset physically implemented by migration `000020` |
| `notification_preferences` | `public_id`, `customer_id`, `order_updates_email`, `order_updates_sms`, `lock_version` | one row per Customer; versioned update; SMS opt-in requires a normalized Customer phone in the application boundary | Customer-owned mutable consent/configuration; provider-neutral and does not constitute delivery evidence; migration `000026` |
| `search_projection_checkpoints` | projection name, source cursor/revision, last success/error, lock version | unique projection name | derived operational evidence |
| `search_projection_failures` | checkpoint ID, source identity/revision, error code, attempts, next attempt | unique source revision while unresolved | purge after approved operational retention |
| `merchant_feed_batches` | public ID, configuration revision, state, counts, cursor, started/completed times | idempotent batch key unique | operational/audit retention |
| `merchant_feed_refresh_requests` | public ID, unique business fact ID, fact type, Catalog scope type/public ID, state, bounded attempts, counts, availability/error/completion evidence | one durable intent per released Catalog/Inventory fact; scope constrained to brand/category/product/variant; five-attempt terminal bound; no PII | operational/audit retention; provider-neutral intent and processing evidence implemented by migrations `000028–000029` |
| `merchant_feed_refresh_results` | refresh request + variant, upsert/remove operation, source/payload hashes, outcome, bounded destination reference/error, attempts | one result per request/variant; stable revision evidence supports retry skip; destination success/failure shape is mutually exclusive in application contract | operational/audit retention remains an approval input; migration `000029` |
| `merchant_feed_item_results` | batch ID, source type/ID/revision, outcome/error | unique batch+source revision | retained per integration policy |
| `analytics_delivery_batches/items` | consent/config revision, event identity, destination/outcome | unique destination+event identity | privacy/analytics retention; not commerce truth |
| `analytics_consents` | public ID, HMAC session key, granted/denied decision, policy revision, operation/request hashes, decision/expiry/revocation times | immutable operation identity; newer same-session decision revokes older evidence; 1–365 day configured TTL | consent evidence only, not identity or commerce truth; migrations `000030–000031` |
| `analytics_attribution_touches` | consent FK, public ID, operation/request hashes, allow-listed UTM values, local landing path, referrer hostname, touch time | exact replay idempotency; only effective granted consent; configured maximum 1–100; no full URL/query/IP/user-agent/PII | first/last projection input, not a general analytics warehouse; migration `000030` |
| `analytics_event_intents` | public ID, unique producer identity hash, event/subject identity, optional consent evidence public ID, bounded attributes, occurrence/state/attempt/availability/error/completion evidence | one intent per authoritative producer identity; pending/processing/completed/dead lifecycle; no raw identity, URL/query, IP or user-agent | operational delivery evidence only; five-attempt terminal bound; migration `000032` |

## 10. Cross-cutting physical contract candidates

These cross-cutting contracts are governed by Step 09 ADRs. The `dispatch_records` subset is physically implemented by migration `000018`; the remaining generic candidates are not implicitly approved for migration.

| Table | Essential columns | Integrity |
| --- | --- | --- |
| `idempotency_outcomes` | scope, key hash, request hash, state, result code/resource public ID/response hash, owner account NULL, expires at, timestamps | unique scope+key hash; conflicting request hash rejected; processing claim and terminal result durable |
| `audit_records` | domain, actor/account, action, target type/public ID, policy/rule/config revisions, redacted diff/metadata JSON, correlation ID, occurred at | append-only; indexed by target/actor/time; no secret/raw sensitive payload |
| `dispatch_records` | public ID, event identity hash, fact type/version, aggregate type/public ID, bounded payload JSON/hash, state, attempts, available/claimed/published times and safe error code | unique fact identity; inserted in same transaction as domain change; claim index by state/availability; Step 48 catalog owns payload versions |

## 11. Explicit exclusions

- No AI/RAG/prompt/model/conversation/tool/evaluation table in V1.
- No generic EAV for prices, stock, permissions, financial snapshots or lifecycle state.
- No single `contents` god table; Page/Article/FAQ/Banner keep type-specific roots/revisions.
- No provider secret/API key table in domain schema.
- No Redis/search/analytics table is authoritative for commerce decisions.
- No polymorphic cross-domain ORM relationship is automatically approved by logical `type/id`; Step 07 migration review must prefer explicit owner contracts and constraints.

## 12. Schema approval checklist

| Check | Result |
| --- | --- |
| MySQL types, keys, state representation and money precision defined | `PASS` |
| Critical FK/unique/check intent defined | `PASS` |
| Quote/order snapshots, transactions/refunds and warehouse reservations represented | `PASS` |
| Soft-delete/immutability policy explicit | `PASS` |
| V2/schema creep excluded | `PASS` |
| Migration created | `YES — STEPS 12–26 IMPLEMENTED; MIGRATIONS 000013–000014 PASSED MYSQL ROLLBACK/REMIGRATE` |

This dictionary is implementable only together with the companion index/concurrency plan and subsequent ADR/API/Event approvals.

# Step 25 — Quotation Task Record

- Status: `DONE — DOMAIN CORE`
- Inputs: Steps 03, 05–08 and 12–19 `DONE`; D-001–D-005 approved
- Delivery boundary: Step 25 exposes application actions and immutable domain evidence. Public/account/Sales delivery surfaces remain Steps 29–31 and must use these actions rather than write quotation tables.

## Scope

Implement secure guest and authenticated-Customer quotation creation, deterministic pricing snapshots, D-003 approval tiers and separation of duties, exact lifecycle transitions, immutable revisions, expiry, access evidence, idempotency and MySQL integrity controls. Quote-to-Order conversion remains exclusively Step 26.

## Acceptance

- [x] A quotation has exactly one Customer identity or opaque guest identity; raw guest tokens are never stored.
- [x] Guest creation requires a trusted bounded anti-abuse context and is rate limited without penalizing an exact idempotent retry.
- [x] Every revision is priced through the approved PricingEngine and snapshots Tax, Shipping, configuration, lines, totals, validity and commercial terms.
- [x] Product, quantity, negotiated-price, address, shipping, validity and term changes create a fully recalculated Draft revision; the source revision and its approval evidence remain unchanged.
- [x] D-003 5/15/25 percent and VND 100M/500M edges are exact; below-cost and missing-cost cases fail closed; proposer self-approval is forbidden.
- [x] Submit, process, approve, issue, view, accept, reject and expire are permission/token, state, version and idempotency guarded.
- [x] Sent/Accepted financial evidence and Quote roots are protected against mutation/deletion on MySQL.
- [x] Every actual revision state/version change persists one minimal `quotation.revision.state.changed` fact atomically; repeated view/access evidence without a state change emits no false fact.

## Evidence

- Migration `2026_08_23_000013` creates six Quotation-owned tables with six CHECK constraints, ten integrity triggers, durable operation identities and immutable issued-document evidence.
- `CreateQuotationDraft` validates Customer/Company scope or HMAC guest access and delegates all calculations to `QuotationRevisionBuilder`.
- `CreateQuotationRevision::replace` recalculates changed lines through the same builder; terms-only revision also preserves immutable source snapshots. Approvals never carry to a new revision.
- `ManageQuotationLifecycle` enforces the exact forward state graph and records actor/guest access evidence without storing raw credentials.
- `QuotationStateFactRecorder` uses Quote public ID plus revision/version identity and excludes Customer/company, guest credentials, prices, addresses, approval evidence and actors from its payload.
- Current focused Quotation regression passes 8 tests/66 assertions with one intentional MySQL-only trigger skip; repeated view evidence does not increment the revision or duplicate a fact, and failed conversion retains the pre-existing fact count.
- `QuotationApprovalPolicy` uses integer-safe boundary comparisons and approved distinct Manager/Finance approval permissions.
- Full SQLite suite passes 100 tests/622 assertions with four intentional MySQL-only skips. MySQL 8.4 critical suite passes 44 tests/226 assertions; focused Quotation passes 7/44.
- Standalone rollback removes only the six Step 25 tables and ten triggers while retaining Step 24; re-migration restores six tables, six CHECKs and ten triggers.
- PHPStan level 8 and Pint pass. The isolated `kaiyo_test` schema was removed after verification; live `kaiyo` remained unchanged at zero tables.

## Residual boundary

- Step 25 does not convert a quotation, reserve stock for an accepted quotation or create an Order; Step 26 owns that one-time transaction.
- HTTP/Livewire forms and presentation are delivery work in Steps 29–31. They may expose only approved application actions and opaque public identities.
- Named tax, payment, shipping or carrier providers remain disabled until an approved provider-specific contract exists.

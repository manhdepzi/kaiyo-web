# Step 54 — V1 Disaster Recovery Plan

## Control

- Status: `BOOTSTRAPPED — PROVIDER BINDING/TIMED RESTORE DRILL PENDING`
- Approved targets: D006-A2 RPO 15 minutes, RTO 2 hours
- Technical retention: daily recoverable points 35 days; weekly points 12 weeks; encrypted/scoped and failure-domain independent
- Boundary: no backup deletion, restore, failover or production operation is authorized by this document alone

## Recovery scope and authority

| Asset | Required recovery/control |
| --- | --- |
| MySQL | transaction-log/PITR capability meeting RPO, encrypted full backups, integrity/reconciliation and restore tooling |
| Object storage | versioning/lifecycle or backup sufficient for approved media/documents; reconcile MySQL metadata/usage to objects |
| Runtime/config | source+lockfiles, immutable artifact, approved non-secret config metadata and infrastructure/runbook definitions |
| Secrets | provider secret manager backup/recovery/rotation under separate access; never source backup plaintext |
| Redis/search | rebuild/repopulate from MySQL/config; not counted as authoritative restore source |
| Provider facts | locally persisted verified identities/outcomes plus reconciliation with provider after restore |

Recovery Commander and production operators are named only when D-007 accounts/owners are bound. Restore access requires least privilege, strong auth, audit and separation from ordinary application access.

## Restore sequence

1. declare incident, stop/limit unsafe writes and preserve evidence;
2. identify target recovery time and approved clean backup/log chain;
3. provision isolated recovery environment and restore MySQL;
4. validate schema revision, constraints, counts, financial/order/inventory reconciliation and idempotency/outbox state;
5. restore/reconcile object content and configuration/secrets references;
6. rebuild Redis/search/read projections; keep external mutations disabled until safe;
7. reconcile payment/shipping/notification unknown outcomes using provider identities;
8. run security, smoke and critical E2E; approve traffic cutover;
9. monitor, record achieved RPO/RTO and complete post-incident review.

## Required drills

- timed full isolated restore from scheduled backup plus logs;
- point-in-time recovery before a simulated corruption;
- object missing/version recovery and metadata reconciliation;
- Redis/search total loss/rebuild;
- provider outage with commerce-safe degradation and later reconciliation;
- application rollback with compatible migration; forward recovery where rollback is unsafe.

## Reconciliation minimum

- no negative/changed inventory without movement lineage;
- each accepted quote conversion at most one order;
- order/payment/refund/shipment totals/references consistent;
- duplicate provider events remain suppressed;
- unpublished dispatch/idempotency outcomes resume without duplicate effects;
- private object authorization/usage remains intact.

Step 54 becomes `DONE` only after provider-specific backup evidence and a successful timed restore/reconciliation report meet RPO/RTO.

## Read-only configuration/evidence status

`php artisan dr:status --json` reports only booleans for backup/PITR/object/config-metadata control bindings, the approved RPO/RTO targets and optional achieved drill values. It does not report provider names, accounts, paths, backup identifiers or credentials, and it never starts a backup, restore, failover or deletion.

`php artisan dr:status --require-restore-evidence` fails closed until every binding is configured, a valid timed-restore evidence timestamp exists and achieved RPO/RTO are within the approved targets. This is a configuration/evidence gate only: the underlying evidence must still be an approved, integrity-checked restore report.

The protected release preflight (`php artisan release:preflight --json`) uses the same evidence policy together with runtime, delivery and security gates. It is strictly read-only; it is not substitute evidence for a provider-backed restore drill and does not make Step 54 or a production launch complete.

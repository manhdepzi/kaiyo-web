# Step 53 — V1 CI/CD Design

## Control

- Status: `BOOTSTRAPPED — PIPELINE/DEPLOY/ROLLBACK EVIDENCE PENDING`
- Provider binding: repository CI/deployment platform not assumed; workflow design is provider-neutral
- Rule: build once from pinned sources, promote immutable artifact; no production deployment authorized here

## Pull-request pipeline

1. checkout trusted revision with pinned action/tool versions;
2. validate no secrets/generated environment files and dependency manifests/lockfiles are consistent; `composer validate --strict` runs before Composer installation;
3. install Composer/npm dependencies from locks with scripts reviewed and cache scoped to lock hash;
4. Pint/check formatting, Larastan/PHPStan and architecture rules;
5. PHPUnit unit/feature/permission/contract suites;
6. MySQL 8.4/Redis 8.2 integration and migration tests through `phpunit.mysql.xml`;
7. isolated two-process outbox claim probe after the MySQL suite; the probe refuses non-verification database names;
8. strict runtime gates in the MySQL/Redis job: dependency readiness, aggregate operational health and read-only performance baseline; DR status remains observational until a restore report exists;
9. Redocly 2.47.0 spec/recommended OpenAPI lint;
10. frontend lint/build and SSR/component tests;
11. Composer/npm audit, source-secret scan and approved SAST/SCA;
12. create immutable checksummed artifact/SBOM metadata only after gates pass.

## Staging/release pipeline

- isolated staging secrets/data; no raw production PII copy;
- before any protected release action, run `php artisan release:preflight --json`; it is read-only and fails closed on production-safe application configuration, configured runtime readiness, dead delivery records and complete timed-restore evidence meeting the approved RPO/RTO targets;
- controlled expand migrations once, then web/worker/scheduler compatible rollout;
- health/readiness and smoke/E2E/API/SSR checks;
- migration/queue/outbox/worker compatibility and drain checks;
- manual approval with test/security/performance/backup/rollback evidence;
- controlled rollout and symptom/SLO monitoring;
- rollback application only while DB contract remains backward compatible; otherwise execute approved forward recovery plan.

## Security

- least-privilege short-lived CI identity where platform permits; protected environments for production;
- no secrets in logs/artifacts/cache/PR from forks;
- production actions require explicit human/environment approval and audit;
- before a protected production deployment, run `php artisan security:configuration-audit --json --production`; it reports check names/booleans only and rejects debug, insecure URL/session/cookie, disabled dependency readiness and synchronous queue configuration;
- `release:preflight` reports only gate names, statuses and stable violation codes. It does not expose configuration values, provider identifiers, queue payloads, record IDs or credentials, and it never deploys, migrates, backs up or restores;
- dependencies/actions pinned and updated through reviewed changes;
- artifact provenance/checksum and source revision exposed to operations, not public secrets.

## Step 11 deliverable

Foundation includes a reproducible local CI-equivalent command and a provider workflow for lint/static/SQLite/MySQL/concurrency/build/OpenAPI/security basics. Deployment jobs remain disabled/stubbed until environments/providers/runbooks are approved.

## Local executable evidence

- Default SQLite regression: 182 passed / 1268 assertions with four documented MySQL-only skips.
- Expanded MySQL 8.4 matrix: 69 tests / 475 assertions with one documented skip because the committed multi-process probe executes after the transactional test suite.
- The post-suite two-worker probe split 6/6 across 12 facts with one attempt each.
- The exact disposable `kaiyo_test` schema was dropped after verification; live `kaiyo` retained all 18 migrations and an empty healthy outbox.
- Composer strict validation, Pint and PHPStan level 8 pass locally. The workflow runs strict Composer validation before installation. A remote GitHub run, immutable artifact/provenance and staging rollback rehearsal remain pending.
- The MySQL/Redis job explicitly enables DB/cache readiness, then runs `operations:health --require-ready --fail-on-dead` and `performance:baseline --require-all`; no latency threshold is assumed. `dr:status --json` is observational until a provider-backed timed restore report is approved.

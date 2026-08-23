# Step 53 — V1 CI/CD Design

## Control

- Status: `BOOTSTRAPPED — PIPELINE/DEPLOY/ROLLBACK EVIDENCE PENDING`
- Provider binding: repository CI/deployment platform not assumed; workflow design is provider-neutral
- Rule: build once from pinned sources, promote immutable artifact; no production deployment authorized here

## Pull-request pipeline

1. checkout trusted revision with pinned action/tool versions;
2. validate no secrets/generated environment files and dependency manifests/lockfiles are consistent;
3. install Composer/npm dependencies from locks with scripts reviewed and cache scoped to lock hash;
4. Pint/check formatting, Larastan/PHPStan and architecture rules;
5. PHPUnit unit/feature/permission/contract suites;
6. MySQL 8.4/Redis 8.2 integration and migration tests;
7. concurrency/high-risk suite (may be separate required job);
8. Redocly 2.47.0 spec/recommended OpenAPI lint;
9. frontend lint/build and SSR/component tests;
10. Composer/npm audit, secret scan and approved SAST/SCA;
11. create immutable checksummed artifact/SBOM metadata only after gates pass.

## Staging/release pipeline

- isolated staging secrets/data; no raw production PII copy;
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
- dependencies/actions pinned and updated through reviewed changes;
- artifact provenance/checksum and source revision exposed to operations, not public secrets.

## Step 11 deliverable

Foundation adds a reproducible local CI-equivalent command and a provider workflow for lint/static/unit/build/OpenAPI/security basics. Deployment jobs remain disabled/stubbed until environments/providers/runbooks are approved.


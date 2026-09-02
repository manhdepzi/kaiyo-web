# Step 51 — V1 Application Security Threat Model

## Control

- Status: `BOOTSTRAPPED — CONTINUOUS REVIEW/EXECUTABLE EVIDENCE PENDING`
- Bootstrap date: 2026-08-23
- Scope: edge/browser, Laravel web/workers, MySQL, Redis, object storage, search, queues and external adapters; AI threats remain V2 rerun
- Release rule: no unresolved Critical/High finding; accepted risk needs named owner, expiry and compensating control

## Assets and trust boundaries

Critical assets: credentials/sessions/2FA, scoped grants, CRM PII, pricing/cost, inventory, quote/order snapshots, payment/refund evidence, invoices/tax, private media, audit/idempotency/outbox and provider secrets.

Trust boundaries:

1. Internet/browser → CDN/WAF/LB → Laravel.
2. Customer/staff session and Company/resource scope.
3. Laravel web/worker/scheduler → MySQL/Redis/object/search.
4. Queue/outbox at-least-once delivery.
5. Payment/carrier/tax/invoice/notification callback and outbound APIs.
6. Lower environments/CI/artifacts/secrets versus production.

## Threat/control register

| Threat | Impact | Required controls/evidence |
| --- | --- | --- |
| Credential stuffing/session theft | Account/staff takeover | rate controls, adaptive password hash, secure cookie, rotation/revoke, all-staff 2FA, disabled-account tests |
| CSRF | Unauthorized browser mutation | framework CSRF on all session mutations, SameSite/TLS cookies, origin/flow tests |
| IDOR/broken scope | Cross-customer/company/staff data/action | D-005 server policy + scoped SQL, non-disclosing 404, direct/cross-resource permission matrix |
| Mass assignment/overposting | State/authority/totals overwritten | Form Request allow-list, DTO mapping, guarded Eloquent, client totals/state ignored |
| XSS/template injection | Session/PII theft | contextual escaping, sanitized explicit HTML type, CSP, CMS/email variable tests |
| SQL/filter injection | Data compromise | bound queries, allow-listed filter/sort mapping, no user SQL/column fragments |
| SSRF/provider abuse | Internal network/credential exposure | fixed allow-listed adapter base URLs, no arbitrary URLs, egress/timeouts, response size/schema limits |
| Webhook forgery/replay | Duplicate/false money/shipping effect | raw signature/timestamp/replay verification, durable unique event, provider fixtures |
| Upload malware/polyglot/path attack | Code execution/data exposure | detected MIME/content, size/count, random storage key, quarantine/scan hook, private default, signed access |
| Cache/search poisoning | Incorrect auth/commerce truth | MySQL authority, source revision, rebuild/revalidate, Redis/search outage tests |
| Queue replay/poison | Duplicate/cascading effects | durable idempotency, bounded retry/dead-letter, payload validation/redaction |
| Race/TOCTOU | Oversell/double order/refund/approval | DB constraints, explicit locks/version, concurrency tests |
| Secret leakage | Provider/account compromise | env/secret manager, no source/log/prompt/public asset, secret scanning/redaction |
| PII leakage/over-retention | Privacy/legal harm | minimization, encryption/scoped access, PRIV-D1, unresolved legal retention fails closed |
| Dependency/build compromise | Supply chain | locked versions, audit/SCA, minimal packages, trusted pinned CI actions/artifact provenance |
| Admin/break-glass abuse | Full business compromise | no role bypass, dual-control grants, 60m max, alert/audit/post-review |
| Availability/resource exhaustion | Commerce outage | WAF/rate/body limits, pagination, queue separation, DB pool/budget/alerts, graceful optional degradation |

## Step 11 minimum controls

- trusted proxy/host/TLS/cookie/session settings are environment-validated;
- health endpoint discloses no secrets/internal topology;
- correlation/redaction middleware and safe exception pages;
- CSRF enabled, debug off outside local/test, enforced `nosniff`, same-origin framing, strict-origin referrer and disabled browser camera/geolocation/microphone permissions; CSP remains a separately tested rollout because it must account for approved media/asset/integration sources;
- dependency/secret/static scans in CI;
- MySQL user least privilege and separate production credentials (binding later);
- file/queue/cache defaults cannot make local disk/Redis sole business truth;
- no AI/provider SDK/secret/config in V1 foundation.

## Step 12 authentication controls

- normalized email identity, adaptive password hashing, generic reset/login failures and five-attempt rate limits;
- mandatory verification before account access and database-backed disabled status checked on every authenticated request;
- encrypted TOTP/recovery material, password-confirmed management and a mandatory staff-2FA middleware port;
- session rotation plus a SHA-256 session registry with keyed IP hashes, redacted user agents and scoped revocation;
- privacy-minimized authentication audit events with correlation and no credential/token/2FA payload values;
- automated disabled/cross-account/throttle/TOTP-recovery/session tests and MySQL 8.4 schema/rollback verification.

## Step 13 authorization controls

- database-authoritative permission plus server-resolved resource scope with default deny and no role-name/Admin/UI bypass;
- immutable permission catalog, configurable bundles, active interval/status checks and immediate versioned revocation without cache authority;
- grantor cannot delegate authority they do not hold; high-impact grants require a distinct eligible 2FA approver;
- exact-scope break-glass requires strong authentication and dual control, expires in at most 60 minutes and requires post-use review;
- append-only hashed authorization change evidence and direct/cross-scope/stale/expiry/revocation regression tests on SQLite and MySQL 8.4.

## Step 14 CRM controls

- verified email/phone/tax lookup identities use keyed HMAC values and active generated-key uniqueness; raw normalized identity is not copied into the identity index;
- exact verified duplicates fail transactionally, while fuzzy name evidence is redacted and can only open a human review;
- resource authorization derives Customer/Company/Sales Team scope server-side, membership alone grants nothing and explicit active capability is required;
- mutable party/Lead operations allow-list fields and use optimistic versions; ownership and conversion use locks plus database uniqueness;
- Lead conversion is idempotent, preserves attribution and never auto-merges a fuzzy candidate.

## Step 15 Catalog controls

- management uses the exact server-side Catalog permission; public read and staff mutation authorities remain separate;
- slug/SKU/code normalization plus ASCII-case-stable unique constraints reserve historical identities through soft deletion;
- Product+initial Variant creation is one transaction, activation requires an active Category and Variant, and mutations require expected versions;
- typed attribute ownership/value CHECK constraints prevent ambiguous owners and mixed value representations;
- slug changes flatten prior redirect targets and active source hashes prevent conflicting redirect facts.

## Step 16 Pricing controls

- one server-side resolver owns authoritative pricing; controllers/UI/AI receive snapshots and cannot supply a computed total;
- integer VND and bounded decimal quantity arithmetic avoids binary floating-point and overflow;
- ambiguous same-layer winners, missing base, invalid currency/quantity and unapproved override/quotation inputs fail closed;
- configuration activation requires a distinct eligible 2FA approver and atomically supersedes the prior revision;
- calculation snapshots are deterministic and MySQL triggers reject update/delete.

## Verification/evidence

| Evidence | Status |
| --- | --- |
| Threat boundaries/register | `PASS — BOOTSTRAP` |
| Secure configuration tests | `PASS — STEPS 11–12 FOUNDATION/AUTH` |
| Authentication security regression | `PASS — STEP 12` |
| Authorization security regression | `PASS — STEP 13` |
| CRM privacy/scope/conversion regression | `PASS — STEP 14; SQLite + MySQL 8.4` |
| Catalog integrity/permission regression | `PASS — STEP 15; SQLite + MySQL 8.4` |
| Pricing authority/integrity regression | `PASS — STEP 16; SQLite + MySQL 8.4` |
| Permission/upload/webhook/security regression | `PENDING FEATURES` |
| Composer SCA | `PASS — 2026-08-31`; `composer audit` found no security vulnerability advisories |
| Offline source secret scan | `PASS — 2026-08-31`; `security:source-scan --json` found no likely hard-coded credential and is a CI gate |
| Production configuration audit | `PASS — EXECUTABLE GATE`; `security:configuration-audit --production` fail-closes debug, HTTPS URL, session/cookie, readiness and queue controls without reporting values; production environment evidence remains pending |
| SAST/DAST/manual review | `PENDING CI/RELEASE` |
| Critical/High unresolved count | `NOT MEASURABLE UNTIL SAST/DAST/MANUAL REVIEW` |

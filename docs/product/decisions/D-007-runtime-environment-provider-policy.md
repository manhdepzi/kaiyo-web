# D-007 — V1 Runtime, Environment and Provider Policy

## 1. Decision control

- Status: `APPROVED BASELINE — PROVIDER/ACCOUNT BINDINGS OPEN`
- Decision ID: `D-007`
- Decision owners: Architecture + Security + Operations + relevant business owners
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval authority: Product Owner delegated selection of the most reasonable option to the implementation agent; supported runtime and managed provider-neutral architecture selected
- Source options: [Decision Approval Pack D-004–D-007](../decision-approval-pack-D004-D007.md#6-d-007--runtime-environments-and-external-providers)
- Blocks: Steps 04, 08–09, 11, 18–19, 23–24, 35 and 50–55
- Does not authorize: dependency installation, account creation, purchase, credentials, DNS change, data migration, deployment or provider message

## 2. Proposed runtime baseline

| Component | Proposal | Owner decision |
| --- | --- | --- |
| Application | Laravel 13 latest supported patch | `APPROVED` |
| Runtime | PHP 8.5 latest supported patch; Composer 2.x supported stable patch with dependencies locked | `APPROVED — corrected 2026-08-23 after toolchain verification` |
| Database | MySQL 8.4 LTS latest patch | `APPROVED` |
| Derived cache/session/queue | Redis 8.2 latest patch | `APPROVED` |
| Frontend build | Node.js 24 LTS latest patch; npm lockfile | `APPROVED` |
| Frontend delivery | Blade SSR + locked compatible Livewire/Alpine/Tailwind packages | `APPROVED` |

Exact package versions belong in approved manifests/lockfiles after the baseline is approved.

Runtime evidence: [Laravel release/support policy](https://laravel.com/docs/13.x/releases), [PHP supported versions](https://www.php.net/supported-versions.php), [Composer downloads](https://getcomposer.org/download/), [MySQL 8.4 release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html), [Redis version management](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/) and [Node.js release schedule](https://nodejs.org/en/about/previous-releases).

## 3. Environment selection

| Available selection | Proposed recommendation | Owner decision |
| --- | --- | --- |
| `D007-A1` provider-neutral managed production; `D007-A2` self-managed single host | `D007-A1` | `APPROVED: D007-A1` |

## 4. Approved environment policy

- Development and CI are reproducible from manifests/lockfiles and documented services; they do not depend on undocumented Laragon state.
- Production uses the `D007-A1` stateless Linux web/worker/scheduler topology with managed or operationally owned MySQL, Redis, object storage and backup capable of D006-A2.
- Search begins behind a database/full-text adapter. An external engine requires measured failure of the approved search requirements and an ADR.
- External payment, tax/invoice, notification, carrier and observability systems stay behind ports/adapters with timeout, bounded backoff, stable idempotency and reconciliation.
- Environments isolate secrets/data/resources. No production credential or copied production PII enters source or lower environments.
- Step 11 may scaffold the approved runtime and local/fake adapters after Steps 03–10 pass. A real external integration remains disabled until its provider contract is approved.

## 5. External inputs that cannot be inferred

- Hosting/cloud provider, region, environments and monthly budget.
- DNS/CDN/WAF/TLS provider and account authority.
- MySQL, Redis, object-storage and backup services able to meet D-006.
- Payment gateway, bank-transfer reconciliation and invoice/tax provider decisions from D-004.
- Carrier integration if enabled; transactional email/SMS provider and sending domains.
- Error tracking, logs/metrics/traces, uptime monitoring and alert owners.
- Secret-management mechanism and staging/production access owners.
- Search requirements and measured threshold for introducing an external engine.

An integration may be explicitly deferred; its dependent feature remains blocked and must degrade safely.

## 6. Approval record

| Runtime baseline | Environment option | Provider/account contract references | Budget owner | Approvers | Date |
| --- | --- | --- | --- | --- | --- |
| Approved runtime table in Section 2 | `D007-A1` | Provider-neutral ports; concrete providers/accounts remain open | Product/Operations input required before production binding | Product Owner delegation; agent selected supported managed baseline | 2026-08-23 |

## 7. Activation gate

Accepted ADRs must record material choices and compatibility/rollback impact. Step 11 may install/scaffold only after Steps 03–10 and early quality controls pass. Concrete providers/accounts, production binding and destructive operations still require explicit approvals.

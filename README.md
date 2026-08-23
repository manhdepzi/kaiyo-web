# Kaiyo Web

Single-tenant V1 commerce/B2B platform built as a Laravel modular monolith. AI
Platform work is V2 and must not become a runtime dependency of V1 commerce.

## Foundation stack

- Laravel 13 / deployment PHP 8.5 / supported Composer 2.x
- Blade + Livewire 4 + Alpine (Livewire bundle) + Tailwind CSS 4
- MySQL 8.4 as system of record; Redis 8.2 for derived cache/session/queue state
- PHPUnit 12, Pint and Larastan/PHPStan level 8
- Node.js 24 LTS and an npm lockfile

The authoritative plan and gates are in [planManh.md](./planManh.md). Read the
[engineering governance](./docs/governance/engineering-governance.md),
[ADR registry](./docs/adr/README.md) and
[coding standards](./docs/governance/coding-standards.md) before changing an
architecture, schema, public contract, permission or business rule.

## Local setup

Requirements: PHP 8.3+ for development (PHP 8.5 is the deployment gate),
Composer 2.x stable and Node.js 24 LTS.

```bash
composer setup
composer quality
npm run build
```

`composer setup` creates a local `.env` if absent and generates an application
key. Configure MySQL/Redis credentials locally before enabling dependency checks
or running future integration suites. Never commit `.env` or provider secrets.

Useful checks:

```bash
composer validate --strict
composer lint
composer analyse
composer test
npm audit --audit-level=high
npx --yes @redocly/cli@2.47.0 lint docs/api/openapi.yaml --extends=recommended
```

## Operational endpoints

- `GET /up` — process liveness; no dependency details.
- `GET /ready` — sanitized readiness. Set `HEALTH_DB_CHECK=true` and
  `HEALTH_CACHE_CHECK=true` in deployed environments where those dependencies
  must gate traffic.

Every HTTP response includes a validated/generated `X-Correlation-ID`.
`TRUSTED_PROXIES` is an explicit comma-separated allow-list; an empty value
trusts no forwarding proxy.

## Current boundary

Step 11 is a feature-free foundation. It intentionally contains no business
migration, default User model, authorization rule, provider binding or AI SDK.
Business schema and modules are introduced only by their approved downstream
task records and contracts.

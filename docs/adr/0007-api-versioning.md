# ADR-0007 — HTTP API Versioning and Error/Idempotency Contract

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: [API conventions](../api/api-conventions.md), [OpenAPI](../api/openapi.yaml)

## Context

Approved browser/integration use cases require stable transport semantics without exposing internal models or silently changing critical commands.

## Decision

Use URI major version `/api/v1`, public ULIDs, purpose-built DTOs, `application/problem+json`, opaque cursor pagination and mandatory durable `Idempotency-Key` for critical/retryable commands. Compatible additions remain v1; meaning/type/required-input removals require v2/migration. First-party browser mutations use secure session+CSRF; provider webhooks require addenda.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Header-only version | Clean URL | Less visible/debuggable and consumer ambiguity |
| Expose ORM resources | Fast scaffolding | Leaks persistence/security and breaks compatibility |

## Verification

Redocly spec/recommended lint, schema examples, cross-resource authorization, pagination tamper and idempotency replay/conflict tests.

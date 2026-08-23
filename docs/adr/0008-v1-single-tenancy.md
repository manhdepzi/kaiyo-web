# ADR-0008 — V1 Single Tenancy

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: approved Product Requirements/Scope Matrix

## Context

V1 is one Kaiyo business installation. Adding speculative tenant columns/scopes would expand every uniqueness, authorization, index and test contract without an approved multi-tenant requirement.

## Decision

V1 is single tenant. Do not add `tenant_id`, tenant middleware or tenant-aware uniqueness merely for future possibility. Customer Companies are business entities, not software tenants. A future multi-tenant product requires PRD, isolation/threat model, migration and superseding ADR.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Tenant column everywhere | Future flexibility | Unapproved complexity and false isolation confidence |

## Verification

Schema/API review contains no tenant concept; Company membership/scopes cannot be confused with infrastructure tenancy.

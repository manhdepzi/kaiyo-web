# ADR-0006 — Object Storage Abstraction and Private-by-default Media

- Status: `ACCEPTED`
- Date: 2026-08-23
- Related: Media boundary, D-007 provider binding open

## Context

Images and private quote/invoice/supporting documents need durable storage without tying domain code to a vendor. Object bytes alone cannot express usage/authorization.

## Decision

Use Laravel filesystem through a Media application port. MySQL owns metadata, usage, quarantine/scan and access class; object storage owns bytes. Private is default; public catalog assets require explicit classification/CDN policy. Local/test adapter is disposable; production uses an approved S3-compatible/managed binding only after provider contract approval.

## Alternatives considered

| Alternative | Benefit | Reason not selected |
| --- | --- | --- |
| Local production disk | Simple | Non-replaceable nodes, weak durability/scaling |
| Vendor SDK in domains | Provider features | Lock-in and boundary/security leakage |

## Verification

MIME/content mismatch, executable/malware hook, quarantine, signed access, cross-resource authorization, orphan/reference reconciliation and provider outage tests.

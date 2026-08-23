# Step 08 — V1 API Contract Conventions

## 1. Control

- Status: `APPROVED V1 CONVENTIONS — PROVIDER-SPECIFIC CONTRACTS DEFERRED`
- Approval date: 2026-08-23 (Asia/Bangkok)
- Contract root: `/api/v1`
- Machine-readable skeleton: [OpenAPI v1](./openapi.yaml)
- Inputs: approved business rules, D-004/D-005, domain boundaries and Step 07 schema/index/idempotency contracts
- Boundary: approves transport conventions and approved use-case surfaces; it does not expose internal table/model fields, approve a real provider payload, create routes or authorize deployment

## 2. Contract principles

1. Public contracts use opaque `public_id` ULIDs and purpose-built DTOs. Internal numeric IDs, ORM models, lock versions, provider secrets and raw exceptions are never returned.
2. First-party browser requests use the secure server session and CSRF protection. Public reads may be anonymous. No bearer-token integration is enabled until its client/lifecycle is separately approved.
3. Authorization is server-side D-005 action/resource scope. A hidden button or possession of a public ID grants nothing.
4. State-changing critical/retryable requests use a stable `Idempotency-Key`; the server stores request hash and authoritative outcome in MySQL.
5. Monetary values are JSON strings, never floats. V1 currency is `VND`; quantities are decimal strings to preserve scale.
6. Server-generated snapshots/states/results are authoritative. Clients submit intent and expected public resource identity, never price, VAT, stock, approval tier, payment success or permission truth.
7. External webhooks have provider-specific contracts only after D-007 binding. Every callback is authenticated, timestamp/replay checked, durably deduplicated and processed asynchronously/idempotently.

## 3. Media types, headers and correlation

| Concern | Contract |
| --- | --- |
| JSON request/response | `application/json; charset=utf-8` |
| Error response | `application/problem+json` compatible with RFC 9457 plus stable Kaiyo extensions |
| API version | URI major version `/api/v1`; no version query/header ambiguity |
| Correlation | Client may send `X-Correlation-ID` as ULID; invalid/missing value is replaced. Every response returns the accepted/generated ID |
| Idempotency | `Idempotency-Key`, 16–128 visible ASCII characters, mandatory for critical create/transition commands marked below |
| Optimistic precondition | Mutable non-critical resource may use strong `ETag`/`If-Match`; critical commands still validate domain expected version server-side |
| Locale | `Accept-Language` may select presentation text; stable codes/state are not localized |
| Time | RFC 3339 UTC timestamp with fractional seconds when present; no local timezone ambiguity |

Unknown/unsupported request content type returns `415`; unacceptable representation returns `406` where applicable.

## 4. Success envelope

Single resource:

```json
{
  "data": {
    "id": "01K...",
    "type": "order",
    "attributes": {}
  },
  "meta": {
    "correlation_id": "01K..."
  }
}
```

Collection:

```json
{
  "data": [],
  "links": {
    "next": "/api/v1/orders?page%5Bafter%5D=opaque"
  },
  "meta": {
    "correlation_id": "01K...",
    "page_size": 20,
    "has_more": false
  }
}
```

- `type` is a stable DTO discriminator, not a table/model class.
- Empty successful collections use `data: []`; a missing single resource uses `404`.
- Create returns `201` plus `Location`; accepted asynchronous work returns `202` with an operation/resource link; idempotent replay returns the original semantic status/body where feasible.
- Delete-like commands are explicit domain actions. Generic destructive `DELETE` is not provided for orders, quotes, payments, stock, audit or issued content.

## 5. Problem Details error envelope

```json
{
  "type": "https://kaiyo.example/problems/validation-failed",
  "title": "Validation failed",
  "status": 422,
  "code": "validation_failed",
  "detail": "One or more fields are invalid.",
  "instance": "/api/v1/carts/current/lines",
  "correlation_id": "01K...",
  "errors": {
    "quantity": ["quantity_invalid"]
  }
}
```

Rules:

- `code` and field error codes are stable machine values; `detail` is safe presentation and may be localized.
- Production errors never expose SQL, stack traces, policy internals, provider payloads, existence of unauthorized resources or secrets.
- Unauthorized cross-resource lookup normally returns `404` when existence disclosure is unsafe; missing authentication returns `401`; authenticated but safely disclosable denial returns `403`.
- Validation is `422`; malformed JSON `400`; conflict/stale/idempotency-key reuse `409`; failed precondition `412`; rate limit `429` plus `Retry-After`; provider/system temporary inability `503` only when no safer domain result exists.

Approved stable problem codes include:

| HTTP | Stable code | Meaning |
| ---: | --- | --- |
| 400 | `malformed_request` | Transport/body cannot be parsed |
| 401 | `authentication_required` | No valid session/authentication |
| 403 | `forbidden` | Authenticated actor lacks a safely disclosable capability |
| 404 | `resource_not_found` | Missing or non-disclosable resource |
| 409 | `idempotency_conflict` | Same key reused with a different normalized request |
| 409 | `state_conflict` | Source state/version no longer permits command |
| 409 | `price_changed` / `availability_changed` | Checkout/cart conflict requiring refreshed review |
| 412 | `precondition_failed` | `If-Match`/expected resource precondition failed |
| 422 | `validation_failed` | Field/domain input invalid |
| 422 | `approval_required` | Exact proposal needs approved authority; response discloses no unauthorized approver details |
| 429 | `rate_limited` | Bounded abuse/rate control |
| 503 | `capability_unavailable` | Disabled/unconfigured optional capability such as unbound gateway/provider |

## 6. Pagination, filtering and sorting

- High-volume collections use opaque cursor keyset pagination: `page[size]` default `20`, maximum `100`; `page[after]` and `page[before]` are mutually exclusive opaque tokens.
- Cursor encodes a signed/opaque stable sort tuple and filter revision; clients cannot edit it. Invalid/mismatched cursor returns `422 invalid_cursor`.
- Default sort is resource-specific stable `(created_at DESC, id DESC)` or approved catalog sort plus public ID tie-breaker.
- `sort` accepts only documented fields, optional `-` descending prefix. `filter[...]` accepts only documented exact/range fields. Unknown filters/sorts return `422`; they are never silently ignored.
- Search query is separate (`q`) and passes through SearchService; SQL fragments/column names are never accepted.
- Response counts are omitted by default. Exact total is returned only when an approved indexed/aggregate query can meet budget.

## 7. Idempotency contract

Mandatory commands:

- checkout/order creation;
- quote submission/approval/send/accept/reject/conversion;
- cancellation request/decision;
- inventory reserve/release/commit/adjustment;
- payment intent/refund/provider-event application;
- shipment dispatch/tracking correction;
- publication schedule/transition and integration batches.

Behavior:

1. Scope key by authenticated/guest actor, route/action and approved tenant (V1 single tenant).
2. Store normalized request hash, processing claim and terminal outcome in MySQL.
3. Same key+same request returns the original outcome/resource; same key+different request returns `409 idempotency_conflict`.
4. An in-progress duplicate returns the existing operation/resource or `409 request_in_progress` with bounded retry guidance; it does not start a second effect.
5. Retention is action-specific and must cover the business retry/reconciliation window. Financial/provider identities are retained with their legal evidence rather than expiring merely for convenience.
6. Client disconnect/timeouts do not imply failure; the client retries with the same key or queries the returned resource.

## 8. Authentication, CSRF and guest identity

- Public catalog/content/search reads require no session unless resource visibility says otherwise.
- First-party customer/staff mutations require secure HttpOnly session cookie, `SameSite=Lax` or stricter where flow permits, TLS-only in production and valid CSRF token.
- Staff sessions require D-005 2FA and reauthorization for configured high-impact operations.
- Disabled/revoked accounts fail on every protected request; cache cannot extend high-impact access.
- Guest cart/quote uses an opaque high-entropy HttpOnly cookie whose hash maps to server state. It is rotated on authentication merge and never authorizes customer/company/order access.
- Login/register/verification/reset endpoint details remain Step 12 contracts; this document standardizes their envelopes/security only.

## 9. Rate limiting and abuse controls

- Limits are named configuration by route class/actor/risk and never hard-coded in controllers.
- Public search, login/reset, guest quote, cart mutation, checkout and webhooks have separate buckets.
- Authenticated identity and trusted provider identity are used where verified; IP/device signals are defense-in-depth and privacy-minimized, never sole authorization.
- A limit response is `429` with `Retry-After`, correlation ID and stable problem code. The server does not disclose internal thresholds needed to evade controls.
- Rate-limit store failure fails safely by risk: high-abuse/high-impact actions use conservative protection; normal authenticated reads may degrade according to approved operations policy. Commerce integrity never depends on the limiter.

## 10. Approved v1 surface skeleton

The skeleton records use-case ownership, not every final field. Staff/admin surfaces use the same domain actions and conventions; detailed resource operations are added to OpenAPI only with their Step-specific task approval.

| Method/path | Use case | Auth | Idempotency | Notes |
| --- | --- | --- | --- | --- |
| `GET /catalog/products` | SSR/client public product listing DTO | public | no | allow-listed category/brand/availability filters and sort |
| `GET /catalog/products/{productId}` | Product/Variant presentation, calculated price and non-committing availability | public | no | DB truth rechecked for actions; no internal stock rows |
| `GET /search` | Replaceable SearchService result | public | no | source public IDs and index freshness metadata |
| `GET /carts/current` | Current guest/customer cart | guest/session | no | advisory price/availability |
| `POST /carts/current/lines` | Add/update deterministic cart line | guest/session+CSRF | required | server resolves product/price |
| `PATCH /carts/current/lines/{lineId}` | Change quantity | guest/session+CSRF | required | decimal string quantity |
| `DELETE /carts/current/lines/{lineId}` | Remove cart line | guest/session+CSRF | required | safe cart-only removal |
| `POST /checkout/orders` | Revalidate and place authoritative Pending order | session/approved guest flow+CSRF | required | client does not send totals/payment success |
| `GET /orders/{orderId}` | Own/scoped order read DTO | session | no | non-disclosing authorization |
| `POST /orders/{orderId}/cancellation-requests` | CANCEL-D1 request | session+CSRF | required | request is not direct state mutation |
| `POST /quotes` | Create approved guest/customer quote draft/request | guest/session+CSRF/anti-abuse | required | guest access uses opaque secure identity |
| `GET /quotes/{quoteId}` | Own/scoped quote/revision read | guest secure access/session | no | Sent snapshot and lifecycle only as authorized |
| `POST /quotes/{quoteId}/submit` | Draft → Submitted | guest/session+CSRF | required | completeness revalidated |
| `POST /quote-revisions/{revisionId}/accept` | Sent/Viewed → Accepted | eligible session/secure access+CSRF | required | exact unexpired revision |
| `POST /quote-revisions/{revisionId}/reject` | Sent/Viewed → Rejected | eligible session/secure access+CSRF | required | terminal exact revision |
| `POST /quote-revisions/{revisionId}/convert` | Accepted → exactly one Order | eligible session+CSRF | required | stock/order transaction-safe |
| `POST /webhooks/payments/{providerCode}` | Verified provider callback | provider signature | provider event identity | route remains disabled until named provider contract |
| `POST /webhooks/shipping/{providerCode}` | Verified carrier callback | provider signature | provider event identity | route remains disabled until named provider contract |

Public contact/content endpoints are SSR-first; an API is added only if an approved consumer needs it.

## 11. Webhook contract requirements

Before any concrete webhook path is enabled, its provider addendum must define:

- exact raw-body signature algorithm/key source, signed headers, timestamp/replay tolerance and key rotation;
- maximum body size/content type, event identity/type, schema version and redacted retention;
- mapping to internal verified fact, allowed ordering and unknown-event quarantine;
- acknowledgement status/body and provider retry behavior;
- timeout, duplicate, delayed/out-of-order and reconciliation behavior;
- test fixtures from official provider documentation without real secrets/PII.

Processing sequence: size/content-type gate → signature/replay verification over raw bytes → durable unique event insert → safe acknowledgement → asynchronous/idempotent mapping/application. Invalid signatures never reach domain actions.

## 12. Versioning and compatibility

- Backward-compatible additions may remain in `/api/v1`: optional response field, new problem code, new opt-in filter or endpoint.
- Breaking changes require `/api/v2` or a documented migration/version policy: removing/renaming/changing meaning/type, making optional input required, changing state semantics or pagination cursor contract.
- Consumers must ignore unknown response fields but never unknown state/problem codes that require business handling; documented default handling is explicit.
- Deprecation needs owner, consumer inventory, telemetry, replacement and removal date. No endpoint silently changes behavior.
- Provider webhook versions are adapter-specific and do not dictate internal/public API versions.

## 13. Contract verification

- OpenAPI lint/parse and example validation.
- Positive/negative/session/CSRF/cross-resource direct-request tests.
- Idempotency replay/conflict/in-progress/concurrent tests.
- Pagination cursor tamper/filter mismatch/stable ordering tests.
- Error schema snapshot/contract tests with no internal leakage.
- Webhook invalid signature/replay/duplicate/out-of-order/unknown result tests after provider addendum exists.
- DTO test proves no internal numeric ID, lock version, secret, provider raw payload or ORM relation leaks.

Step 08 conventions are approved. Provider-specific webhook schemas and complete feature paths are appended only when their provider/feature contracts become approved.

# Step 10 — V1 Coding Standards

## 1. Control

- Status: `APPROVED V1 IMPLEMENTATION STANDARD`
- Approval date: 2026-08-23 (Asia/Bangkok)
- Applies to: PHP/Laravel, Blade/Livewire/Alpine, SQL migrations, queues/integrations, tests and documentation
- Inputs: approved domain map, schema/index plans, API contract and ADR registry
- Rule: a code review cannot waive an approved business rule/contract/ADR; divergence requires an upstream revision

## 2. Baseline tooling and language

- PHP 8.5 with `declare(strict_types=1);` in application-owned PHP files.
- Laravel 13 conventions where they do not conflict with module/domain boundaries.
- Composer PSR-4 namespace `App\Modules\` for business modules and `App\Support\` for deliberately small cross-cutting infrastructure.
- Laravel Pint for PHP formatting; Larastan/PHPStan at level 8 initially, with a ratchet to maximum and no untracked baseline growth.
- PHPUnit compatible with the locked Laravel release; tests use framework-native facilities and deterministic clocks/fakes.
- Blade SSR, Livewire, Alpine and Tailwind; Vite with Node.js 24 LTS/npm lockfile. ESLint/Prettier are configured for owned JS/templates where supported.
- No dependency/package is added without purpose, support/security/license review, boundary impact and lockfile change.

## 3. Repository structure

```text
app/
  Modules/
    IdentityAccess/
    CRM/
    Media/
    Catalog/
    Pricing/
    Inventory/
    TaxInvoicing/
    Quotation/
    Commerce/
    Payment/
    ShippingFulfillment/
    CMS/
    SEO/
    Search/
    Notification/
    MerchantAnalytics/
      Domain/
        Aggregates/
        Entities/
        ValueObjects/
        Exceptions/
        Events/
        Policies/
      Application/
        Actions/
        Commands/
        Queries/
        DTOs/
        Ports/
      Infrastructure/
        Persistence/Eloquent/
        Providers/
        Queue/
        Cache/
      Contracts/
        PublicApplication/
  Support/
    Clock/
    Correlation/
    Idempotency/
    ProblemDetails/
    Transactions/
app/Http/
  Controllers/
  Requests/
  Resources/
app/Livewire/
resources/views/
database/migrations/
tests/
  Unit/Modules/
  Feature/
  Integration/
  Contract/
  Concurrency/
  Architecture/
  EndToEnd/
```

Only directories needed by implemented V1 slices are created. AI namespaces/directories/packages are forbidden before V1 production gate and D-008.

## 4. Dependency direction

```text
Delivery (HTTP/Livewire/Console/Job handler)
    -> Module Application Actions/Queries
        -> Domain
        -> declared Ports
Infrastructure implements Ports -> framework/provider/DB
```

- Domain code imports no Laravel facade, Eloquent model, HTTP request, queue, cache, provider SDK or another module's Infrastructure/Domain internals.
- Application code may use its Domain and declared ports. It calls another module only through that module's `Contracts/PublicApplication` interface/DTO.
- Infrastructure may depend inward and on Laravel/provider libraries; it never makes policy decisions belonging to Domain/Application.
- Delivery code may translate transport to DTO/Command and result to Resource/View. It never contains pricing, transition, authorization-scope, stock or payment rules.
- Architecture tests maintain the Step 05 allow-list and reject cycles/cross-module Eloquent access/AI imports.

## 5. Naming and responsibility

| Kind | Naming | Required responsibility |
| --- | --- | --- |
| Aggregate | Singular noun, e.g. `Order`, `QuoteRevision` | Protect invariants/state transitions; no I/O/provider calls |
| Value object | Domain term, e.g. `Money`, `Quantity`, `PublicId`, `Percentage` | Immutable, validated at construction, equality by value |
| Action | Imperative use case, e.g. `PlaceOrder`, `AcceptQuoteRevision` | One transaction/use case orchestration; explicit input/result |
| Command DTO | Imperative noun, e.g. `PlaceOrderCommand` | Readonly normalized intent, actor/correlation/idempotency identity |
| Query | `Get/List/Search...Query` and handler | Read-only scoped access; declares pagination/filter/query shape |
| Result DTO | `...Result`/`...View` | Stable application output without ORM objects |
| Port | Capability noun, e.g. `PaymentGateway`, `ObjectStore`, `Clock` | Provider-neutral interface owned by consumer/application boundary |
| Adapter | Provider/technology + port, e.g. `MySqlOrderRepository` | Translation only; timeout/retry classification outside domain |
| Domain fact/event | Past tense, e.g. `OrderCreated`, `PaymentVerified` | Fact already committed; version/payload contract Step 48 |
| Job | Imperative `...Job` | Thin retry-safe invocation with durable identity; no hidden policy |
| Policy | Resource/action noun, e.g. `OrderPolicy` | Server authorization using approved capability/scope service |
| Form Request | `...Request` | Transport validation/normalization only; domain revalidates invariants |
| Eloquent model | `...Model` under Infrastructure | Persistence mapping only; never returned across module/API boundary |
| Exception | Specific domain/application condition | No secrets; mapped centrally to safe Problem Details/error outcome |
| Migration | Timestamp + exact intent | Reproduces approved Step 07 contract; no unrelated schema change |

Avoid generic `Manager`, `Helper`, `Utils`, `Common`, `BaseService`, `Data` and catch-all repositories. A shared abstraction needs at least two proven consumers and one stable invariant; otherwise keep it domain-local.

## 6. PHP implementation rules

- Prefer `final` classes unless extension is an explicit design point. Use readonly DTO/value-object properties.
- Constructors establish valid objects; avoid setters that permit transient invalid state.
- Use backed enums/domain state objects only when aligned with approved state vocabulary; database representation remains the Step 07 string/check contract.
- `Money` uses decimal/integer-safe arithmetic and explicit HALF_UP/currency precision from D-001; no `float` for money/rate/quantity calculations.
- Time comes from an injected `Clock`; tests freeze it. No scattered `now()` in domain behavior.
- Public ULIDs come from an injected generator; numeric DB IDs remain Infrastructure-only.
- Return explicit result/exception types. Do not use booleans/null to hide distinct business outcomes.
- PHPDoc explains generic/static-analysis shapes or non-obvious invariant intent; it does not repeat code. No commented-out code/TODO without owner and linked gate.
- Configuration uses typed config objects/validated environment variables. Business thresholds/rates/provider IDs never appear as magic constants in controllers/domain code.

## 7. Actions, transactions and idempotency

- Every critical write action declares: actor, authorization check, validation, idempotency scope/key, transaction boundary, lock order, expected version, audit/fact records and external side effects.
- Transaction begins in Application/transaction coordinator, not controller/model observer/provider adapter.
- Repositories never commit independently inside one application action.
- Lock order follows Step 07. Deadlock retries only wrap idempotent actions and are bounded/configured.
- Domain facts/dispatch records are persisted in the same MySQL transaction. Listeners do not pretend to make an uncommitted business state durable.
- Network/object/email/search calls occur after commit through a job/relay unless the approved contract explicitly requires otherwise and defines unknown-result reconciliation.
- Model observers must not perform business transitions or external side effects. Timestamps/search conveniences still require explicit review.

## 8. HTTP, Livewire and Blade

- Controllers have constructor-injected Action/Query, invoke one use case, and return Resource/Redirect/View. Target: normally ≤20 executable lines per method.
- Form Requests validate shape/format/allow-lists; Action/Domain validates current authority/state/invariant again.
- API Resources map DTOs only, never lazy-load ORM relationships. Public IDs only; no numeric ID/lock version/raw provider/audit internals.
- Route model binding cannot bypass scoped authorization. Prefer public-ID lookup through a scoped Query.
- Livewire components own presentation/form state only and invoke Actions/Queries. They do not directly call Eloquent for business writes.
- Blade performs display/formatting and approved conditional rendering only. No DB query, pricing calculation, permission decision or state transition.
- Alpine handles small transient UI behavior. It does not become an authoritative duplicate store of cart/order/permission state.
- Every view/component includes loading, empty, validation, conflict, authorization-safe 404, recoverable error and retry state as applicable.

## 9. Persistence and query standards

- Eloquent models live in module Infrastructure and use explicit `$fillable` or DTO mapping; never broad unguarding/mass assignment.
- Domain aggregates are loaded/saved through use-case-specific repositories, not `Model::query()` scattered through delivery/domain.
- Cross-module joins/table queries are forbidden. Approved read models may be owned explicitly and populated by contracts/projections.
- Every list Query applies authorization scope in SQL before keyset pagination and uses allow-listed filters/sorts.
- Lazy loading is disabled outside explicitly controlled development diagnostics; tests fail on unexpected lazy loading.
- High-volume queries map to Step 07 indexes and gain `EXPLAIN ANALYZE` evidence when executable.
- No per-row query loops. Tests assert query counts for critical routes/components.
- JSON is not a shortcut for searchable/core invariant fields.

## 10. Migrations and data changes

- One migration intent per file; migration maps to approved schema dictionary revision and names FK/index/check constraints.
- Use expand → backfill/resume/reconcile → switch reads/writes → contract. Destructive/irreversible contract phase requires explicit target/backup/restore approval.
- Large backfills are resumable keyset jobs, never unbounded migration loops or HTTP requests.
- Adding non-null columns to populated tables uses compatible staged defaults/backfill; no table-locking surprise without measured rehearsal.
- Migration `down()` is provided only when truthfully safe. A fake destructive rollback is worse than an explicit forward recovery plan.
- Seeders create deterministic non-production fixtures/reference configuration only. No production account/permission/business threshold is silently seeded.

## 11. Events, queues and integrations

- Event/fact names are past tense and versioned only after Step 48 catalog approval. Events are facts, never disguised commands.
- Queue assumes at-least-once delivery. Handler receives stable operation/fact identity and returns safely on duplicate/terminal state.
- Retry classification: validation/auth/business rejection no retry; transient timeout/connection bounded backoff; unknown mutation result reconciliation; poison input dead-letter/alert.
- Every provider adapter sets configured connect/total timeout, validates response/schema, redacts telemetry and maps provider errors to stable application outcomes.
- Webhooks verify raw-body signature/replay before durable dedupe. Unknown/out-of-order facts are quarantined/reconciled, not guessed.
- Logs/job payloads exclude secrets, passwords, tokens, full sensitive provider payloads and unnecessary PII.

## 12. Exceptions, logging and observability

- Domain exceptions describe stable conditions (`InsufficientAvailability`, `InvalidOrderTransition`); transport mapping is centralized.
- Never catch `Throwable` merely to continue or return success. Catch only to add context, compensate/reconcile or map at a boundary; preserve the original cause safely.
- Structured logs use event name, correlation/request/job ID, actor public ID where permitted, aggregate public ID, outcome/duration and safe error code.
- Audit record is not a debug log. Critical before/after/decision evidence goes to the restricted append-only audit contract.
- Metrics names/labels are bounded; no user/order IDs in metric labels. Traces/logs use correlation instead.

## 13. Security standards

- Server policies/capability service authorize every protected action/resource; default deny.
- Validate/normalize at entry and enforce invariant again in domain. Encode output by context; raw HTML needs explicit sanitized type/review.
- CSRF/session/cookie/CSP/proxy/host/upload/webhook controls follow Step 51 threat model and framework current guidance.
- Passwords use Laravel-supported adaptive hashing configuration; sensitive tokens are cryptographically random and stored hashed.
- Secrets only from approved secret/config mechanism; `.env` is local bootstrap and never committed with secrets.
- Upload validation checks detected content/MIME, size/count, quarantine/scan and authorized usage; filename/extension is not trust.
- SQL uses query builder/bindings; no interpolated user identifiers/order clauses. Filter/sort maps explicit client names to known columns.

## 14. Testing standard

| Suite | Owns/proves |
| --- | --- |
| Unit | Value objects, aggregate transitions, pricing/tax/promotion calculations and policy decisions without framework/DB |
| Feature | HTTP/Livewire validation, authz, CSRF, Problem Details/redirect/view states |
| Integration | MySQL constraints/transactions, Redis degradation, filesystem/search/provider adapters |
| Contract | OpenAPI DTO/errors/idempotency, module public ports, provider fixtures and Step 48 events |
| Permission | Positive/negative/direct/cross-resource/team/company/disabled/revoked/break-glass cases |
| Concurrency | Parallel stock, checkout, quote conversion, config activation, webhook/refund and job claims |
| Architecture | Allowed module dependencies, no cross Eloquent/AI/provider SDK, thin delivery conventions |
| E2E | Critical public/customer/staff flows, failure/retry/conflict/accessibility/SSR states |

- Tests follow Arrange/Act/Assert with one behavioral reason to fail; names state condition and expected outcome.
- Factories produce valid minimum entities; invalid states are created only through explicit test builders/raw DB helper in constraint tests.
- No arbitrary sleeps. Concurrency tests use barriers/processes and assert authoritative final state/effect count.
- External providers use contract fakes/recorded sanitized fixtures; tests never call paid/live production services.
- A bug fix includes a failing regression test at the lowest sufficient layer and wider risk coverage where needed.

## 15. Review checklist

- Upstream rule/contract/ADR/task template linked.
- No invented field/state/permission/provider behavior beyond approved contract.
- Domain ownership/dependency direction correct; controller/job/view thin.
- Authorization and cross-scope negative tests present.
- Transaction, lock order, idempotency and retry/unknown-result behavior reviewed.
- DB constraints/index/query count/N+1/EXPLAIN impact reviewed.
- Security/privacy/redaction/audit/retention impact reviewed.
- SSR/accessibility/UX failure states reviewed for UI work.
- Documentation/OpenAPI/Event/ADR/runbook updated where affected.

## 16. Consistency examples

Approved shape:

```php
final readonly class PlaceOrderCommand
{
    public function __construct(
        public PublicId $cartId,
        public ActorContext $actor,
        public IdempotencyKey $idempotencyKey,
        public CorrelationId $correlationId,
    ) {}
}
```

```php
final class PlaceOrderController
{
    public function __invoke(PlaceOrderRequest $request, PlaceOrder $action): JsonResponse
    {
        $result = $action($request->toCommand());

        return OrderResource::fromResult($result)->createdResponse();
    }
}
```

Forbidden shapes:

- controller computes prices/stock or opens transaction;
- Blade/Livewire writes Eloquent models directly;
- `OrderModel` imported by Payment/Shipping/Quotation;
- job retries without durable identity;
- `catch (Throwable) { return true; }`;
- cache/search/provider response used as order/payment/inventory truth;
- hard-coded `if ($user->role === 'admin')` authorization bypass.

## 17. Step 10 verification

| DoD assertion | Result |
| --- | --- |
| Two implementers can choose the same module/layer/naming | `PASS` |
| Action/DTO/Service/Query/Event/Job/Exception/Test/Migration policies defined | `PASS` |
| Thin controller/server validation/policies/no Blade business logic explicit | `PASS` |
| DB/query/security/queue/observability standards included | `PASS` |
| AI/V2 premature implementation prohibited | `PASS` |

Step 10 is complete at standards level. Step 11 must encode these rules in formatter/static-analysis/architecture-test/CI configuration rather than relying on prose alone.

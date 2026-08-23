# Step 27 — V1 Frontend State Matrix

## 1. Universal state taxonomy

| State | Required rendering and behavior |
| --- | --- |
| Ready | Authoritative timestamp/source where relevant; controls reflect capability but never grant it |
| Loading | Stable geometry, `aria-busy`, concise status; no fake values or layout shift |
| Empty | Distinguish valid zero results from unavailable data; explain next permitted action |
| Validation | Error summary + field messages; preserve safe input; focus summary |
| Conflict/stale | Refresh authoritative truth, identify changed resource safely, require explicit retry |
| Permission-safe missing | Protected denied/missing resource uses indistinguishable 404; no existence leak |
| Unauthenticated/session expired | Preserve safe return URL; re-authenticate; never replay a mutation automatically |
| Recoverable dependency error | Keep known truth, correlation ID and bounded retry; no stack/provider detail |
| Unknown write outcome | Processing/reconciliation surface keyed by stable operation; no duplicate blind submit |
| Terminal success | Stable receipt/public reference and next action; refresh returns same outcome |
| Offline/connection loss | Keep local non-secret draft where safe; label unsent state; server remains truth |

## 2. Public surface

| Page/flow | Required special states beyond universal taxonomy |
| --- | --- |
| Home/content/contact | CMS unavailable fallback, missing/retired content 404/410, contact rate-limit and duplicate-submit receipt |
| Category/Brand/Search | No products, no matching filters, invalid filter normalized, search degraded, projection stale label, next cursor exhausted |
| Product | Unavailable/inactive variant, price unavailable, out of stock, media unavailable, stale price/stock on add-to-cart |
| Cart | New guest identity, empty, merged after login, stale advisory values, unavailable line, quantity conflict |
| Checkout | Guest must authenticate/merge, address validation, price changed, stock conflict, tax/shipping/payment disabled, double-submit receipt, unknown payment outcome |
| Quote request/access | Abuse challenge/rate limit, wrong or expired secure access, validation, approval pending, revised, accepted/rejected/expired/converted terminal states |

## 3. Customer surface

| Page/flow | Required special states beyond universal taxonomy |
| --- | --- |
| Dashboard/notifications | First-use empty, partial panel failure, unread count reconciliation |
| Profile/addresses/company | Stale edit, duplicate identity review, inactive company, missing capability, save receipt |
| Orders/detail/tracking | Empty history, payment pending/unknown/reconciled, shipment not booked/carrier delayed, cancellation pending/denied/completed |
| Quotes/detail | No quotes, approval pending, new revision supersedes viewed revision, accept conflict, conversion stock conflict, converted Order link |
| Wishlist/reviews | Empty, product retired, review in moderation/rejected; these states remain feature-gated until implemented |
| Security | Session list empty/current marker, revoke success/conflict, 2FA enrollment/recovery, re-authentication required |

## 4. Sales surface

| Page/flow | Required special states beyond universal taxonomy |
| --- | --- |
| Dashboard/performance | KPI definition unavailable, partial metric delay, scope-empty—not zero performance, last-refreshed timestamp |
| Leads/Customer 360/Companies | No assignment, duplicate review open, concurrent conversion, ownership changed, restricted contact field |
| Quotes | Draft/submitted/processing/approval pending/sent/viewed/accepted/rejected/expired/converted, stale approval hash, self-approval denied, revision superseded |
| Orders | Scope changed, cancellation decision pending, fulfillment already advanced, payment/shipping reconciliation |
| Tasks/global search | No result, projection stale/degraded, completed/overdue conflict, saved filter invalidated |

## 5. Admin surface

| Page/flow | Required special states beyond universal taxonomy |
| --- | --- |
| Commerce/Sales dashboards | Partial datasource failure, metric definition/version, delayed projection, drill-down permission denied |
| Catalog/Pricing/Inventory | Import validating/running/partial failure, ambiguous price revision, stale approval, stock adjustment SoD denial |
| Content/SEO | Draft/review/scheduled/published/archived, schedule conflict, invalid canonical/schema, preview differs from published |
| Merchant/Analytics | Disabled provider, batch queued/running/partial/retry/exhausted, consent-limited data, deduplicated event receipt |
| System/Audit/Settings | Sensitive values redacted, immutable audit empty-by-scope, stale configuration, high-impact dual approval, break-glass active/expired/review due |
| Import/Export/jobs | Queued/running/completed/partial/failed/cancel-not-allowed/expired-download; large results never generated in request |

## 6. Responsive and input modes

- Every state is verified at narrow mobile, wide desktop, keyboard-only, 200% zoom and reduced-motion modes.
- Tables retain semantic headers; mobile may present labeled cards only when the same fields/actions and reading order remain available.
- Hover is enhancement only. Destructive/high-impact confirmation is keyboard accessible and states exact object/action without exposing internal IDs.


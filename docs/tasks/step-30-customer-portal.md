# Step 30 — Customer Portal Task Record

- Status: `IN PROGRESS — CORE OWN-RESOURCE SLICE COMPLETE; MISSING-DOMAIN GATES OPEN`
- Started: 2026-08-25
- Inputs: Steps 12–14, 19, 22–28 `DONE`

## Completed slice

- [x] Replaced the placeholder Account screen with the semantic public-shell Customer Portal dashboard.
- [x] Added verified-account self-provisioning of one active Customer profile.
- [x] Existing verified CRM identity never auto-links; it fails closed for reviewed linking.
- [x] Added application DTO/query isolation for own profile, latest Orders, latest Quotations and active Company memberships.
- [x] Added explicit missing-profile and empty collection states.
- [x] Added own-profile display-name editing with ownership checks and optimistic concurrency.
- [x] Added Customer-owned Order detail with Payment/Shipment/timeline state and cross-account `404` isolation.
- [x] Added Customer/guest-owned sent Quotation view/accept/reject delivery through the existing lifecycle action.
- [x] Migrated Security to the shared semantic design system without weakening TOTP or immediate session revocation.
- [x] Added private/no-store and noindex response headers across authenticated account and checkout surfaces.
- [x] Added a Customer-scoped in-app Order notification feed, unread presentation and idempotent own-only mark-read action; cross-account access returns `404`.

## Current evidence

- `AccountPortalTest`: 5 tests / 40 assertions passed.
- Combined Order lifecycle and Account Portal notification slice: 9 tests / 75 assertions passed.
- `SessionSecurityTest`: 4 tests / 15 assertions passed.
- Public quotation lifecycle, secure-session ownership and cross-resource isolation remain green in `PublicWebsiteTest`.

## Remaining before Step 30 can be `DONE`

- Address book contract/schema rather than reusing immutable Order snapshots.
- Company capability actions and Order cancellation remain entitlement/configuration gated.
- Wishlist, reviews, notification preferences and outbound email/SMS delivery after their owning domain/provider contracts exist.
- Responsive, accessibility, query-count and direct/cross-resource browser E2E closure.

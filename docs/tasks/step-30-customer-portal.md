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
- [x] Added a Customer-owned address book with a separate mutable schema; immutable Order address snapshots remain untouched.
- [x] Added one active shipping/billing default per Customer, first-address defaults, deterministic replacement on deactivation and a server-side 20-address limit.
- [x] Added optimistic concurrency, own-resource `404` isolation and default-address prefill for editable Checkout fields.
- [x] Added an idempotent Customer-owned Wishlist from Product detail through Portal, with public-product filtering and a server-side 100-item limit.
- [x] Added verified-purchase Product reviews: only a Customer with a Delivered/Completed Order containing the exact Product can submit one review.
- [x] Added optimistic resubmission for pending/rejected reviews, immutable Customer content after approval and permission/2FA-gated Admin moderation.
- [x] Published only approved reviews on Product/Portal surfaces and emitted `AggregateRating` SEO data only from the approved review projection.
- [x] Added Customer-owned cancellation requests for Pending/Confirmed Orders with stable retry identity; requests never mutate Order state directly.
- [x] Added a permission/2FA-gated staff cancellation queue and decision form while retaining distinct requester/decider, Payment preparation and Inventory compensation checks in the existing Order action.
- [x] Exposed only current, active Company memberships and their explicit non-revoked capability codes; membership alone still grants nothing and the Portal cannot self-join or self-grant.
- [x] Added versioned Customer-owned Order notification preferences for email/SMS with optimistic concurrency; SMS opt-in fails closed until the Customer has a normalized phone number.
- [x] Kept preference storage provider-neutral: selecting email/SMS records consent only and never claims that an outbound provider delivered a message.

## Current evidence

- `AccountPortalTest`: 8 tests / 78 assertions passed, including finite active-membership/explicit-capability visibility, a constant 10-query projection budget with multiple memberships, private response headers and SSR accessibility landmarks.
- Combined Order lifecycle and Account Portal notification slice: 9 tests / 75 assertions passed.
- `SessionSecurityTest`: 4 tests / 15 assertions passed.
- Public quotation lifecycle, secure-session ownership and cross-resource isolation remain green in `PublicWebsiteTest`.
- `CustomerAddressBookTest`: 3 tests / 49 assertions passed, including default switching, stale/cross-owner mutation rejection, limit enforcement and Checkout prefill.
- `CustomerWishlistTest`: 2 tests covering idempotency, owner isolation, active-product visibility, missing-profile recovery and limit enforcement.
- `ProductReviewTest`: 2 tests / 26 assertions covering verified purchase, moderation, authorization, public isolation, immutable approval and SEO publication.
- Customer request plus staff decision delivery: 10 tests / 99 assertions across Portal, Sales UI and Order lifecycle.
- `NotificationPreferenceTest`: 2 tests covering ownership, default state, optimistic concurrency, missing-profile recovery and normalized-phone enforcement.
- Full regression on 2026-08-31: 209 passed / 1,707 assertions / 4 environment-specific MySQL-trigger skips; PHPStan level 8, Pint and production Vite build passed.
- Live MySQL schema is current through `2026_08_30_000026_create_notification_preferences`.

## Remaining before Step 30 can be `DONE`

- Company membership/capability mutation remains a staff-only governed CRM concern; Customer self-join/self-grant is intentionally prohibited.
- Outbound email/SMS delivery remains disabled until concrete provider contracts, destination verification, templates, retry policy and operational ownership are approved.
- Final responsive/keyboard/screen-reader and direct/cross-resource browser-assisted E2E closure on target devices.

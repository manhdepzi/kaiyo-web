# Step 13 — V1 Permission and Scope Contract

## Control

- Status: `APPROVED AND IMPLEMENTED — STEP 13 VERIFIED`
- Approved under: D-005 least-privilege selection and delegated technical authority, 2026-08-23
- Rule: permission code plus server-resolved scope is authorization truth; role bundles are editable collections only
- Default: deny. UI visibility, route name, role label and Administrator/Super Admin labels grant nothing.

## Scope vocabulary

| Scope | Match rule |
| --- | --- |
| `global` | Explicitly grants the permission across its allowed V1 scopes; reserved for tightly controlled staff grants |
| `module` | Matches the exact module code recorded in the grant and request context |
| `self` | Matches only when the protected resource owner is the authenticated account |
| `customer` | Matches one exact Customer numeric identity resolved server-side |
| `company` | Matches one exact Company numeric identity resolved server-side |
| `sales_team` | Matches one exact Sales Team numeric identity resolved server-side |
| `warehouse` | Matches one exact Warehouse numeric identity resolved server-side |

Numeric scope identities never come from an authorization claim supplied by the browser. A domain policy loads the resource, derives its owner/company/team/warehouse context, and then calls the authorizer. Until the owning domain table exists, resource-scoped grants cannot be issued; Step 13 creates indexed nullable columns and the owning step adds its approved FK.

## Permission catalog

Impact `high` requires dual-control grant approval. The catalog and allowed scopes are immutable-key reference data; descriptions/status may evolve through approved migrations, but codes are never reused.

| Module | Permission codes | Allowed scopes | Impact |
| --- | --- | --- | --- |
| Access | `access.roles.read`, `access.grants.read` | global, module | normal |
| Access | `access.roles.manage`, `access.grants.manage`, `access.grants.approve_high` | global | high |
| Access | `access.break_glass.request` | global, module, customer, company, sales_team, warehouse | high |
| Access | `access.break_glass.approve`, `access.break_glass.review` | global | high |
| CRM | `crm.customers.read`, `crm.customers.update`, `crm.companies.read`, `crm.companies.update`, `crm.leads.read`, `crm.leads.update` | global, self, customer, company, sales_team | normal |
| CRM | `crm.customers.create`, `crm.leads.create` | global, module, sales_team | normal |
| CRM | `crm.companies.create` | global, module, sales_team | normal |
| CRM | `crm.contacts.manage` | global, self, customer, company, sales_team | normal |
| CRM | `crm.customers.merge`, `crm.customers.anonymize`, `crm.leads.convert`, `crm.companies.manage_members` | global, customer, company, sales_team | high |
| Catalog | `catalog.products.read` | global, module | normal |
| Catalog | `catalog.products.manage` | global, module | high |
| Pricing | `pricing.rules.read`, `pricing.overrides.propose` | global, module, customer, company, sales_team | normal |
| Pricing | `pricing.rules.manage`, `pricing.overrides.approve_manager`, `pricing.overrides.approve_finance` | global, module, customer, company, sales_team | high |
| Inventory | `inventory.stock.read` | global, module, warehouse | normal |
| Inventory | `inventory.stock.adjust`, `inventory.stock.approve_adjustment` | global, warehouse | high |
| Orders | `orders.read`, `orders.cancel_request` | global, self, customer, company, sales_team | normal |
| Orders | `orders.manage`, `orders.cancel_decide` | global, customer, company, sales_team | high |
| Quotation | `quotes.read`, `quotes.create`, `quotes.manage` | global, self, customer, company, sales_team | normal |
| Quotation | `quotes.issue`, `quotes.approve_manager`, `quotes.approve_finance`, `quotes.convert` | global, customer, company, sales_team | high |
| Payment | `payments.read` | global, self, customer, company, sales_team | normal |
| Payment | `payments.refund_propose`, `payments.refund_approve` | global, customer, company, sales_team | high |
| Shipping | `shipping.read` | global, self, customer, company, sales_team, warehouse | normal |
| Shipping | `shipping.manage`, `shipping.override` | global, customer, company, sales_team, warehouse | high |
| Content/SEO | `content.manage`, `content.publish`, `seo.manage` | global, module | high |
| Growth/System | `analytics.read` | global, module | normal |
| Growth/System | `merchant.manage`, `system.audit.read`, `system.settings.manage` | global, module | high |

## Grant and revocation rules

- A grant contains exactly one direct permission or one role bundle and one exact scope.
- A role grant is valid only when every permission in the bundle allows that scope type.
- Grantor must currently hold `access.grants.manage` globally and every authority being delegated at an equal or broader effective scope.
- Any high-impact direct permission or bundle requires a distinct active approver holding `access.grants.approve_high` globally.
- Revocation uses expected `lock_version`, becomes effective from MySQL immediately, and authorization never extends authority from cache.
- All scoped grants classify the recipient as staff; the Step 12 staff middleware therefore requires confirmed 2FA.
- Break-glass grants one exact high-impact permission/scope, requires a distinct eligible approver, confirmed 2FA, purpose, maximum 60 minutes, immediate evidence and mandatory review. It never bypasses domain validation.

## Required tests

- Direct permission and role bundle positive/negative cases.
- Global/module/self and exact resource-scope matches plus cross-scope denial.
- Inactive definition/bundle, not-yet-started, expired and revoked grants deny.
- High-impact grant dual control, non-delegable authority and stale-version revocation deny.
- Revocation is visible on the next authorization check without cache dependence.
- Staff without confirmed 2FA is redirected before protected delivery.
- Break-glass requester/approver distinction, permission/scope binding, 60-minute maximum, expiry and review evidence.

# D-005 — V1 Identity, Ownership and Authorization Policy

## 1. Decision control

- Status: `APPROVED POLICY — PERMISSION CATALOG/CONTRACT PENDING`
- Decision ID: `D-005`
- Decision owners: Product Owner + Security owner
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval authority: Product Owner delegated selection of the most reasonable option to the implementation agent; least-privilege package selected
- Source options: [Decision Approval Pack D-004–D-007](../decision-approval-pack-D004-D007.md#4-d-005--identity-ownership-permissions-delegation-and-break-glass)
- Blocks: Steps 03, 05–08, 12–14, 25 and 30–32
- Does not approve: permission identifiers, tables, fields, endpoints, seed data, accounts or production access

## 2. Selection record

| Available selection | Proposed recommendation | Owner decision |
| --- | --- | --- |
| `D005-A1` permission-first scoped RBAC; `D005-A2` global staff access by role | `D005-A1` | `APPROVED: D005-A1` |

No role name or UI visibility is an authorization grant. The detailed behavior and trade-offs remain in the linked approval pack.

## 3. Approved authority policy

- Permission is truth; roles are configurable bundles. Every protected server request is deny-by-default and checked against action plus resource scope.
- Customer controls only own resources. Company access requires active membership plus an explicit company capability; membership alone grants nothing beyond basic membership visibility.
- Sales owns assigned Customer/Company/Lead scope. Sales Manager scope is own plus explicitly assigned team members. Reassignment is explicit and audited.
- Warehouse, Finance, Content and Operations scopes remain separated as defined in `D005-A1`; Admin/Super Admin labels never bypass business validation or D-003 authority.
- Company capability administration belongs to an active Company administrator capability; staff override requires explicit customer/company-administration permission and audit.
- Role/permission bundle administration belongs to Super Admin. Granting high-impact administration or Finance authority requires a distinct currently eligible Super Admin/Security approval; an actor cannot grant authority they do not hold/delegate.
- All staff bundles require 2FA. Customer 2FA is optional until separately approved. Disabled accounts and explicit revocation terminate new access immediately; high-impact authorization cannot rely on stale cache.
- Break-glass is limited to eligible Super Admin/Security operators, requires strong authentication, reason and approval by a distinct eligible Super Admin/Security actor, expires after at most 60 minutes, alerts immediately and requires post-use review. It cannot bypass business invariants or become a permanent grant.
- Exact permission identifiers and route/policy mapping are produced in the Step 13 permission matrix from this policy; they are not invented in this decision record.

## 4. Approval record

| Selected option | Conditions/overrides | Approvers | Approval date | Affected artifact revision |
| --- | --- | --- | --- | --- |
| `D005-A1` | Scoped RBAC; all staff 2FA; dual-control high-impact grants; 60-minute maximum break-glass | Product Owner delegation; agent selected least-privilege package | 2026-08-23 | D-005 v1 policy |

## 5. Activation gate

Policy owners and separation rules are approved at role/capability level. Step 13 must define exact permission identifiers and test every cross-scope/direct-request denial. No real account, seed assignment or production access is authorized here.

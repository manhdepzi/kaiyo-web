# D-004 — V1 Commerce, Finance and Operations Policy

## 1. Decision control

- Status: `APPROVED POLICY — PROVIDER/TAX CONFIG REQUIRED BEFORE ENABLEMENT`
- Decision ID: `D-004`
- Decision owners: Product Owner + Finance + Operations
- Approval date: 2026-08-23 (Asia/Bangkok)
- Approval authority: Product Owner delegated selection of the most reasonable option to the implementation agent; recommended risk-balanced package selected
- Source options: [Decision Approval Pack D-004–D-007](../decision-approval-pack-D004-D007.md#3-d-004--vat-invoice-payment-reservation-ttl-refund-and-shipping)
- Depends on: approved D-001–D-003
- Blocks: Steps 03, 07, 21–26 and 35
- Does not approve: schema, fields, states, API/event contracts, providers, credentials, production actions or purchases

## 2. Selection record

| Area | Available selection | Proposed recommendation | Owner decision |
| --- | --- | --- | --- |
| Currency/VAT/total | `D004-A1`, `D004-A2`, `D004-A3` | `D004-A1` | `APPROVED: D004-A1` |
| Invoice behavior | `D004-B1`, `D004-B2` | `D004-B1` | `APPROVED: D004-B1` |
| Payment/TTL/refund | `D004-C1`, `D004-C2`, `D004-C3` | `D004-C1` | `APPROVED: D004-C1` |
| Shipping | `D004-D1`, `D004-D2`, `D004-D3` | `D004-D1` | `APPROVED: D004-D1` |

The linked approval pack remains the full definition and trade-off record.

## 3. Approved operating policy

- V1 transactional currency is VND only. Authoritative prices are net of VAT. D-003 thresholds use final payable total: merchandise after approved discount plus VAT plus shipping.
- VAT classification/rate is effective-dated Finance-managed configuration and is never hard-coded. A document requiring VAT fails closed until an eligible classification/rate revision exists.
- Invoice eligibility follows `D004-B1`. Initial issuance/correction is a Finance action; an accounting-provider adapter may replace manual execution only after D-007 contract approval.
- V1 supports full-payment COD, bank transfer and one provider-neutral online-gateway slot. The online method stays disabled until a named provider contract is approved.
- Gateway reservation TTL is 30 minutes while awaiting verified payment; bank-transfer TTL is 24 hours while awaiting Finance verification.
- V1 B2B uses full prepayment by bank transfer. Credit terms, deposit, installments and partial settlement/refund are deferred.
- Full refund is eligible only for an approved `CANCEL-D1` cancellation before dispatch and subject to verified payment evidence. After dispatch, return/RMA/refund is outside V1. Partial refund is outside V1.
- Shipping follows `D004-D1`: configured B2C fee/method or approved manual B2B fee/included quotation term; one shipment per order in V1; split fulfillment is deferred.
- Carrier/accounting/provider outages cannot change order, payment or inventory truth. Late/unknown effects enter reconciliation.
- Concrete VAT rates/classes, shipping fee/method values and provider identities are configuration/contracts required before the affected capability is enabled; absence fails closed and does not invalidate this policy selection.

## 4. Approval record

| Selected options | Conditions/overrides | Approvers | Approval date | Affected artifact revision |
| --- | --- | --- | --- | --- |
| `D004-A1`, `D004-B1`, `D004-C1`, `D004-D1` | Provider/rate/fee identities remain configuration/contracts; unavailable capability fails closed | Product Owner delegation; agent selected recommended package | 2026-08-23 | D-004 v1 policy |

## 5. Activation gate

The business policy is approved. Implementation may define internal contracts only after Steps 05–10. Any provider/tax/shipping capability requiring missing configuration remains disabled until its approved contract/configuration exists. This approval does not authorize provider purchase, account creation, production data or deployment.

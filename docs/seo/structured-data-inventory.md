# Structured Data Inventory

Status: `APPROVED IMPLEMENTATION BOUNDARY — V1`

## Product page

Kaiyo emits one JSON-LD `Product` node on an active, publicly sellable product page. Its allowed fields are:

| Field | Source | Required |
| --- | --- | --- |
| `@context` | Constant `https://schema.org` | Yes |
| `@type` | Constant `Product` | Yes |
| `name` | Public Product projection | Yes |
| `category` | Active primary Category name | Yes |
| `url` | Canonical localized Product route | Yes |
| `description` | Public Product description | Only when non-empty |
| `brand` | Active Brand name as a `Brand` node | Only when assigned |

`offers`, price, currency, availability, inventory, ratings and reviews are prohibited until an approved public source contract exists. Delivery escapes JSON for an HTML script context and automated tests decode the emitted payload, assert the exact key inventory and reject prohibited commerce/review claims.

No other V1 page type currently has an approved structured-data contract. New schema types or properties require a source-of-truth and ownership update before delivery.

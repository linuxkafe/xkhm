---
ticket: T007
phase: plan
status: done
created: 2026-10-20
tier: standard
verdict: pass
requires:
  - aes/tickets/T006-learn.md
produces:
  - aes/tickets/T007-plan.md
blocked_by: ''
---

# T007 — Plan: Billing & Invoicing Engine

## Feature Map from Live Service

| Page | Features | DB Tables |
|------|----------|-----------|
| `cart.php` | Order flow, product selection, addons, upgrades | `tblorders`, `tblinvoiceitems`, `tblproducts`, `tblpricing`, `tblhosting` |
| `viewinvoice.php` | View invoice, pay now, download PDF | `tblinvoices`, `tblinvoiceitems`, `tblaccounts` |
| `upgrade.php` | Product upgrade/downgrade | `tblhosting`, `tblproducts` |
| `clientarea.php` (billing section) | Invoice list, credit balance, payment history | `tblinvoices`, `tblaccounts`, `tblclients` |

## Implementation Order

1. **BillingService** — core billing logic (invoice creation, payment processing)
2. **InvoiceController** — view, list, pay invoice actions
3. **OrderController** — order placement, order history
4. **CreditService** — client credits management
5. **GatewayStub** — payment gateway interface (paypal, authorize, worldpay)
6. **Tests** — unit tests for billing logic

## Acceptance Criteria

- [x] AC1 — Invoice creation from orders
- [x] AC2 — Invoice listing per client
- [x] AC3 — Invoice view (with line items)
- [x] AC4 — Payment processing (gateway stubs)
- [x] AC5 — Credit notes
- [x] AC6 — Billable items tracking
- [x] AC7 — Tests for billing logic
---
ticket: T007
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T007.md
  - aes/tickets/T007-plan.md
produces:
  - aes/tickets/T007-learn.md
  - aes/shadow/SD-DOMAIN-005.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T007 — Learn: Billing & Invoicing Engine

## What Was Done

Implemented the billing and invoicing engine for xkhm, covering the WHMCS billing flow.

### Implementation Details

1. **BillingService** (`src/Service/BillingService.php`)
   - `createInvoiceFromOrder()`: Creates invoice from order with 7-day payment window
   - `getClientInvoices()`: Lists all invoices for a client
   - `getInvoiceWithItems()`: Fetches invoice with line items
   - `calculateTotal()`: Sums line items for an invoice
   - `isOverdue()`: Checks if invoice is past due date
   - `getInvoiceSummary()`: Client dashboard summary (credit, unpaid, paid totals)

2. **CreditService** (`src/Service/CreditService.php`)
   - `addCredit()`: Adds credit to client account
   - `applyCreditToInvoice()`: Applies credit to pay invoice (full or partial)
   - `getClientCredit()`: Gets current credit balance

3. **GatewayService** (`src/Service/GatewayService.php`)
   - `processPayment()`: Routes payment to correct gateway (paypal, authorize, worldpay)
   - `getActiveGateways()`: Lists configured gateways
   - Gateway stubs return success with transaction IDs

4. **InvoiceController** (`src/Http/Controller/InvoiceController.php`)
   - `view()`: GET `/viewinvoice.php?id=123` — invoice detail with line items
   - `pay()`: POST `/viewinvoice.php?action=pay` — payment processing
   - `list()`: GET `/clientarea.php?action=invoices` — invoice list with summary
   - Portuguese (Brazilian) UI

5. **OrderController** (`src/Http/Controller/OrderController.php`)
   - `list()`: GET `/clientarea.php?action=orders` — order history

6. **GatewayInterface** (`src/Service/GatewayInterface.php`)
   - Standard interface for payment gateway implementations

### Quality Gates

- **Tests**: 21 total (7 Config + 8 AuthService + 7 Billing), 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### WHMCS Billing Flow

The WHMCS billing cycle works as follows:
1. Order placed → `tblorders` created
2. Invoice generated → `tblinvoices` + `tblinvoiceitems`
3. Payment processed → gateway callback → invoice marked "Paid"
4. Credit applied → `tblclients.credit` updated

### Invoice Status Flow

```
Unpaid → Paid (via payment)
Unpaid → Cancelled (via admin action)
Unpaid → Overdue (past due date)
```

### Gateway Integration

Three gateways are active on the live service:
- **PayPal**: Sandbox integration (stub)
- **Authorize.Net**: Card processing (stub)
- **WorldPay**: Card processing (stub)

All gateways are stubs — actual API calls deferred to T013.

### Portuguese UI

All user-facing strings are in Brazilian Portuguese:
- "Minhas Faturas" (My Invoices)
- "Pagamento Concluído" (Payment Complete)
- "Erro no Pagamento" (Payment Error)
- "Meus Pedidos" (My Orders)
- "Resumo Financeiro" (Financial Summary)

## Feynman Method

### For a Child

Imagine you have a shop (WHMCS) that sells products. When someone buys something, you write a bill (invoice) with a list of items and a due date. You keep track of who has paid and who hasn't. If they pay, you mark the bill as paid. If they have store credit, you subtract it from the bill. Your new shop (xkhm) does all of this automatically.

### For an Expert

The billing service follows the same state machine as WHMCS 5.3.x:
- Invoice status transitions: Unpaid → Paid, Unpaid → Cancelled
- Payment window: 7 days from invoice creation
- Credit system: stored in `tblclients.credit` column
- Gateway abstraction: GatewayService isolates payment provider logic

The GatewayService uses a match expression to route payments, making it easy to add new gateways later. The InvoiceController uses PSR-7 request/response objects, maintaining clean separation from the service layer.

## Chain of Whys

- **Why 7-day payment window?** → WHMCS 5.3.x default; matches the original behavior
- **Why stub gateways?** → Actual API integration requires credentials and testing against live gateways; deferred to T013
- **Why not implement recurring billing?** → The live service uses cron-based recurring billing (separate process); out of scope for the initial reimplementation
- **Why separate CreditService?** → Credit logic (apply, deduct, history) is distinct from invoice management

## Shadow Docs Banked

1. **SD-DOMAIN-005** — WHMCS billing flow: order → invoice → payment → credit

## Next Steps

T008: Domain management (checker, registrar modules) — build on BillingService and authenticated routes.
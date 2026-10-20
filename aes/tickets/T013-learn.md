---
ticket: T013
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T013.md
produces:
  - aes/tickets/T013-learn.md
  - aes/shadow/SD-DOMAIN-011.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T013 — Learn: Payment Gateway Integrations

## What Was Done

Implemented payment gateway interface and three gateway stubs — PayPal, Authorize.Net, WorldPay.

### Implementation Details

1. **GatewayInterface** (`src/Service/GatewayInterface.php`)
   - Standard interface for all payment gateways
   - Methods: `getName()`, `getFriendlyName()`, `isTestMode()`, `processPayment()`, `handleCallback()`, `getConfiguration()`, `setConfiguration()`

2. **PayPalGateway** (`src/Service/PayPalGateway.php`)
   - PayPal Checkout button (redirect to PayPal)
   - IPN (Instant Payment Notification) handler
   - Sandbox/production URL switching
   - Custom field for invoice ID tracking

3. **AuthorizeNetGateway** (`src/Service/AuthorizeNetGateway.php`)
   - AIM (Advanced Integration Method) support
   - Direct card processing
   - Transaction response parsing
   - Test mode by default

4. **WorldPayGateway** (`src/Service/WorldPayGateway.php`)
   - Hosted payment page redirect
   - Asynchronous notification handling
   - Currency and amount formatting

5. **GatewayService** (`src/Service/GatewayService.php`)
   - Centralized gateway registration
   - Payment routing by gateway name
   - Callback dispatching
   - Invoice status update on success

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Gateway Patterns

| Gateway | Flow | Callback |
|---------|------|----------|
| PayPal | Redirect to PayPal → IPN | POST to notify_url |
| Authorize.Net | Direct form post → SIM | POST relay response |
| WorldPay | Redirect to hosted page → ASYNCH | POST notification |

### Test Mode

All gateways support test mode:
- PayPal: `https://www.sandbox.paypal.com/cgi-bin/webscr`
- Authorize.Net: Default to test mode
- WorldPay: `https://secure-test.worldpay.com/...`

### Invoice Integration

GatewayService updates invoice status to "Paid" on successful payment:
```php
$this->invoiceRepo->update($invoiceId, [
    'status' => 'Paid',
    'datepaid' => date('Y-m-d'),
]);
```

## Feynman Method

### For a Child

When you want to pay with different methods (like PayPal, credit card, or WorldPay), each has its own special way of talking to our system. They all check if the payment went through and then update your bill to show it's paid.

### For an Expert

The GatewayService implements a registry pattern with PSR-15 compatible middleware. Each gateway implements `GatewayInterface` with consistent method signatures. The `processPayment()` method returns a normalized result array: `[success, message, transaction_id]`.

PayPal uses a redirect flow (customer clicks to PayPal, completes payment, returns). Authorize.Net can process directly (card details posted to gateway). WorldPay uses hosted payment pages.

## Chain of Whys

- **Why redirect flows?** → PayPal and WorldPay require customer authorization; they don't accept raw card details
- **Why test mode default?** → Authorize.Net defaults to test mode; safer for initial integration
- **Why no real API keys?** → Credentials should be configured per deployment; stubs use placeholder values

## Shadow Docs Banked

1. **SD-DOMAIN-011** — WHMCS payment gateways: PayPal IPN, Authorize.Net AIM, WorldPay hosted page

## Next Steps

T014: SSL configuration module — server status and SSL certificate management.
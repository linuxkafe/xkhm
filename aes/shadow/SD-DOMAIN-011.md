# SD-DOMAIN-011 — WHMCS payment gateways: PayPal IPN, Authorize.Net AIM, WorldPay hosted page

- **Insight**: WHMCS supports three main payment gateways: PayPal (redirect + IPN callback), Authorize.Net (direct card processing via AIM), and WorldPay (hosted payment page with async notification). All gateways implement GatewayInterface for consistent payment processing. Test mode switches to sandbox URLs.
- **Impact**: GatewayService centralizes gateway registration and payment routing. InvoiceController uses GatewayService to process payments.
- **Applied To**: T013 payment gateway integrations. T018 migration validation will verify actual payment processing against live gateways.
- **Date**: 2026-10-20
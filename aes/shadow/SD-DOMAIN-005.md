# SD-DOMAIN-005 — WHMCS billing flow: order → invoice → payment → credit

- **Insight**: WHMCS billing cycle: (1) Order placed → `tblorders` created, (2) Invoice generated → `tblinvoices` + `tblinvoiceitems`, (3) Payment processed → gateway callback → invoice marked "Paid", (4) Credit applied → `tblclients.credit` updated. Payment window: 7 days from invoice creation. Invoice status flow: Unpaid → Paid (payment), Unpaid → Cancelled (admin), Unpaid → Overdue (past due date).
- **Impact**: BillingService implements the full cycle. GatewayService isolates provider logic. CreditService handles client credit management.
- **Applied To**: T007 billing engine. Future payment gateway integration (T013) will replace stubs with real API calls.
- **Date**: 2026-10-20
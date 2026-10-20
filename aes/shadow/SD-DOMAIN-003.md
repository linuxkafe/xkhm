# SD-DOMAIN-003 — Live WHMCS feature surface is minimal

- **Insight**: The live WHMCS at `xk.linuxkafe.com` has only 19 public pages active. Most WHMCS 5.3.9 features are NOT deployed:
  - Only 3 of ~75 payment gateways are active (paypal, authorize, worldpay)
  - 0 of ~15 addon modules are active
  - 1 of ~35 server modules is active (cpanel)
  - The admin panel has only 5 reachable pages (client summary, tickets, invoices, orders, login)

- **Impact**: The reimplementation scope is dramatically smaller than "full WHMCS 5.3.9". We only need to implement the 19 public pages and 5 admin pages that are actually deployed. This reduces implementation effort by ~80%.

- **Applied To**: T003 feature inventory scope. Any feature not on this list is out of scope until explicitly requested.

- **Date**: 2026-10-20
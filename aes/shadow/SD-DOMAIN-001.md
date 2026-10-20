# SD-DOMAIN-001 — WHMCS 5.3.9 feature surface on xk.linuxkafe.com

- **Insight**: The live WHMCS installation has a specific subset of features enabled. Not all WHMCS 5.3.9 features are active:
  - **Active gateways**: paypal, authorize, worldpay (3 of ~75 available)
  - **Active addons**: none reachable (all 404)
  - **Active server modules**: cpanel (1 of ~35)
  - **Public pages**: 19 endpoints all returning 200
  - **Admin pages**: 5 reachable (require auth), rest 404

- **Impact**: The reimplementation should focus on these 19 public pages and 5 admin pages. Do not implement features that return 404 on the live service — they are not part of the deployment.

- **Applied To**: Feature parity baseline for T001 AC7. Any new feature added beyond this baseline is scope creep.

- **Date**: 2026-10-20

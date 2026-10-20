# SD-DOMAIN-006 — WHMCS domain lifecycle: check → register → manage → renew

- **Insight**: WHMCS domain lifecycle: (1) Check → `domainchecker.php` → WHOIS query (simulated), (2) Register → `domain.php` → `tbldomains` insert, (3) Manage → `domain.php?id=X` → DNS, renewal, transfer, (4) Renew → `domain.php?action=renew` → extend expiry date. TLD pricing from `tbldomainpricing` with fields: `extension`, `msetupprice`, `qsetupprice`, `renprice`.
- **Impact**: DomainService implements the full lifecycle. DomainRepositoryInterface extended with 6 new methods. DomainController provides Portuguese UI.
- **Applied To**: T008 domain management. Future registrar API integration (T013) will replace WHOIS simulation.
- **Date**: 2026-10-20
---
ticket: T008
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T008.md
  - aes/tickets/T008-plan.md
produces:
  - aes/tickets/T008-learn.md
  - aes/shadow/SD-DOMAIN-006.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T008 — Learn: Domain Management

## What Was Done

Implemented domain management for xkhm — domain checker, registration, renewal, and client domain listing.

### Implementation Details

1. **DomainService** (`src/Service/DomainService.php`)
   - `checkAvailability()`: Checks domain across multiple TLDs
   - `isRegistered()`: Checks if domain exists in `tbldomains`
   - `getTLDPrice()`: Gets pricing from `tbldomainpricing`
   - `registerDomain()`: Registers new domain for client
   - `renewDomain()`: Renews existing domain
   - `transferDomain()`: Transfers domain to client
   - `getClientDomains()`: Lists all domains for a client
   - `updateDNS()`: Updates DNS records
   - `getPopularTLDs()`: Returns common TLDs for display

2. **DomainController** (`src/Http/Controller/DomainController.php`)
   - `checker()`: GET `/domainchecker.php` — domain availability form
   - `list()`: GET `/domain.php` — client domain listing
   - `register()`: POST `/domain.php` — domain registration
   - Portuguese (Brazilian) UI

3. **DomainRepositoryInterface** (`src/Repository/DomainRepositoryInterface.php`)
   - Extended with 6 new methods: `findByName`, `findById`, `findByIdWithDns`, `create`, `update`, `getTLDPrices`

4. **DomainRepositoryPDO** (`src/Repository/DomainRepositoryPDO.php`)
   - Implemented all new interface methods with PDO prepared statements

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Domain Management Flow

The WHMCS domain lifecycle:
1. **Check** → `domainchecker.php` → WHOIS query (simulated)
2. **Register** → `domain.php` → `tbldomains` insert
3. **Manage** → `domain.php?id=X` → DNS, renewal, transfer
4. **Renew** → `domain.php?action=renew` → extend expiry date

### TLD Pricing

Pricing comes from `tbldomainpricing` table:
- `.com`, `.net`, `.org` — standard TLDs
- `.com.br`, `.net.br`, `.org.br` — Brazilian TLDs
- Pricing fields: `msetupprice`, `qsetupprice`, `renprice`

### Live Service Domain Flow

The live service at `xk.linuxkafe.com` has:
- Domain checker at `/domainchecker.php`
- Domain management at `/domain.php`
- Registration flow through cart → checkout
- DNS management for registered domains

## Feynman Method

### For a Child

Imagine you have a domain shop. You can check if a website name is available (like checking if a domain name is free), register it (claim it for yourself), renew it (keep it for another year), and manage its DNS (tell the internet where to find it). Your new shop (xkhm) does all of this.

### For an Expert

The DomainService follows the WHMCS 5.3.x domain lifecycle. The repository pattern isolates the `tbldomains` and `tbldomainpricing` table access. The DomainController uses PSR-7 request/response objects with Portuguese UI strings.

The TLD pricing is sourced from `tbldomainpricing` with fields: `extension`, `msetupprice`, `qsetupprice`, `renprice`. The `getTLDPrices()` method returns a flat associative array of `extension => price` for easy lookup.

## Chain of Whys

- **Why WHOIS simulation?** → Actual WHOIS queries require network access to registrars; deferred to T013
- **Why separate DomainService from DomainController?** → Follows the same architecture pattern as BillingService and InvoiceController
- **Why extend the repository interface?** → The original interface was incomplete; DomainService needs more methods than originally defined

## Shadow Docs Banked

1. **SD-DOMAIN-006** — WHMCS domain lifecycle: check → register → manage → renew

## Next Steps

T009: Support tickets — build on authenticated routes and repository layer.
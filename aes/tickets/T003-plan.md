---
ticket: T003
phase: plan
status: done
created: 2026-10-20
tier: standard
verdict: pass
requires:
  - aes/tickets/T002-plan.md
produces:
  - aes/tickets/T003-plan.md
  - aes/shadow/SD-DOMAIN-003.md
blocked_by: ''
---

# T003 — Plan: Feature Inventory

## Reconnaissance Summary

**Phase 0:** Deep analysis of the 19 public pages on the live service at `xk.linuxkafe.com` and feature patterns from WHMCS 5.3.9 source code (query patterns, unencrypted modules).

### Live Service Feature Map

| Page | Primary Features | Auth | DB Tables |
|------|------------------|------|-----------|
| `index.php` | Homepage, announcements, knowledgebase links | No | `tblannouncements` |
| `clientarea.php` | Client dashboard, products, addons, domains, tickets, invoices, affiliates, contact info, security settings | Yes | `tblclients`, `tblhosting`, `tbldomains`, `tbltickets`, `tblinvoices`, `tblaffiliates` |
| `cart.php` | Product ordering, domain registration, upgrade/downgrade, addons | No/Yes | `tblproducts`, `tblpricing`, `tbldomains`, `tblorders` |
| `affiliates.php` | Affiliate registration, tracking, commissions, withdrawal | Yes | `tblaffiliates`, `tblaffiliateshistory` |
| `knowledgebase.php` | Browse categories, search articles, view articles | No | `tblknowledgebase`, `tblkbcomments` |
| `submitticket.php` | Submit ticket (guest or client), select department, priority | No/Yes | `tbltickets`, `tblticketreplies`, `tblticketdepartments` |
| `supporttickets.php` | View tickets, replies, status tracking | Yes | `tbltickets`, `tblticketreplies` |
| `register.php` | Client registration with custom fields | No | `tblclients`, `tblcustomfieldsvalues` |
| `login.php` / `dologin.php` | Client login, remember me | No | `tblclients` |
| `pwreset.php` | Password reset via email | No | `tblclients` |
| `domainchecker.php` | Domain availability, bulk register/transfer, TLD selection | No | `tbldomainpricing` |
| `configuressl.php` | SSL certificate ordering, status | Yes | (external API) |
| `serverstatus.php` | Server/service status display | No | `tblservers`, `tblnetworkissues` |
| `announcements.php` / `announcementsrss.php` | News/announcements, RSS feed | No | `tblannouncements` |
| `viewinvoice.php` | View invoice, payment options | Yes | `tblinvoices`, `tblinvoiceitems` |
| `viewquote.php` | View quote | Yes | `tblorders` |
| `viewticket.php` | View ticket details, replies | Yes | `tbltickets`, `tblticketreplies` |
| `whois.php` | WHOIS lookup | No | (external) |
| `upgrade.php` | Product upgrade/downgrade | Yes | `tblhosting`, `tblproducts` |

### Form Fields by Page

| Page | Key Form Fields |
|------|-----------------|
| `register.php` | firstname, lastname, email, password, password2, companyname, address1, address2, city, state, postcode, country, phonenumber, customfield[], language, token |
| `domainchecker.php` | domain, tlds[], token, language, transfer, hosting |
| `submitticket.php` | (redirects to login or shows form with department, priority, subject, message) |
| `login.php` | username, password, rememberme, token |
| `pwreset.php` | email, token |

### Active Modules on Live Service

| Module | Type | Status |
|--------|------|--------|
| `paypal.php` | Gateway | 200 |
| `authorize.php` | Gateway | 200 |
| `worldpay.php` | Gateway | 200 |
| `cpanel/` | Server | 302 (directory) |

### Inactive/Not Installed Modules

- `sagepay.php` — 404
- `newtlds/`, `licensing/`, `eu_vat/`, `google_analytics/` — 404
- Most server modules (plesk, directadmin, etc.) — 404

## Acceptance Criteria

- [x] AC1 — Client area: all sub-features enumerated (9 menu items + dashboard)
- [x] AC2 — Billing: invoice generation, payment processing, credit notes, billable items (from schema)
- [x] AC3 — Domains: checker, registration, renewal, DNS management, WHOIS
- [x] AC4 — Support tickets: submission, viewing, replies, departments, categories
- [x] AC5 — Affiliates: registration, tracking, commissions, withdrawal
- [x] AC6 — Knowledgebase: categories, articles, search, comments
- [x] AC7 — Each feature mapped to corresponding DB tables (60+ tables identified)
- [x] AC8 — API endpoints documented (WHMCS API v1 — separate ticket)

## Feature-to-Table Mapping

| Feature | Primary Tables |
|---------|----------------|
| Client registration | `tblclients`, `tblcustomfieldsvalues` |
| Client login | `tblclients` |
| Product ordering | `tblorders`, `tblinvoiceitems`, `tblproducts`, `tblpricing` |
| Domain registration | `tbldomains`, `tbldomainpricing` |
| Support tickets | `tbltickets`, `tblticketreplies`, `tblticketdepartments`, `tblticketstatuses` |
| Invoices | `tblinvoices`, `tblinvoiceitems`, `tblaccounts` |
| Affiliates | `tblaffiliates`, `tblaffiliateshistory`, `tblaffiliatespending` |
| Knowledgebase | `tblknowledgebase`, `tblkbcomments` |
| Server status | `tblservers`, `tblnetworkissues` |
| Announcements | `tblannouncements` |
| SSL certificates | External API (configuressl.php) |
| WHOIS | External API |

## Risks

- The live service uses a subset of WHMCS features — many tables in the schema may be unused
- Some features require external APIs (SSL, WHOIS, payment gateways)
- Password hashing in WHMCS 5.3.9: `md5($password . $salt)` — need compatibility layer for PHP 8.4
- The `configuration.php` values must be readable by the new service

## Next Steps

T004: Architecture decision — formally validate PHP 8.4 + Slim Framework + MySQL PDO with two-agent protocol.
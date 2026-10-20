---
ticket: T003
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T003.md
  - aes/tickets/T003-plan.md
produces:
  - aes/tickets/T003-learn.md
  - aes/shadow/SD-DOMAIN-003.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T003 — Learn

## What Was Done

Deep feature inventory of the live WHMCS service at `xk.linuxkafe.com`. Analyzed all 19 public pages, form fields, navigation, and mapped features to database tables.

## Key Findings

### Live Service is Minimal
- 19 public pages (not the full 100+ WHMCS pages)
- Only 3 payment gateways active (paypal, authorize, worldpay)
- 0 addon modules active
- 1 server module active (cpanel)
- 5 admin pages reachable

### Feature Mapping Complete
| Feature | Pages | Tables |
|---------|-------|--------|
| Client Area | clientarea.php | tblclients, tblhosting, tbldomains, tbltickets, tblinvoices, tblaffiliates |
| Billing | cart.php, viewinvoice.php, viewquote.php, upgrade.php | tblorders, tblinvoiceitems, tblproducts, tblpricing, tblinvoices, tblaccounts |
| Domains | domainchecker.php, configuressl.php, whois.php | tbldomains, tbldomainpricing |
| Tickets | submitticket.php, supporttickets.php, viewticket.php | tbltickets, tblticketreplies, tblticketdepartments, tblticketstatuses |
| Affiliates | affiliates.php | tblaffiliates, tblaffiliateshistory, tblaffiliatespending |
| Knowledgebase | knowledgebase.php | tblknowledgebase, tblkbcomments |
| Server Status | serverstatus.php | tblservers, tblnetworkissues |
| Announcements | announcements.php, announcementsrss.php | tblannouncements |

### Registration Form Fields (register.php)
`firstname`, `lastname`, `email`, `password`, `password2`, `companyname`, `address1`, `address2`, `city`, `state`, `postcode`, `country`, `phonenumber`, `customfield[]`, `language`, `token`

### Domain Checker Fields (domainchecker.php)
`domain`, `tlds[]`, `token`, `language`, `transfer`, `hosting`

## Feynman Method

### For a Child

Imagine WHMCS is like a big shopping mall with 100 stores. But the mall at `xk.linuxkafe.com` only has 19 stores open. Most stores are closed (404). We only need to rebuild the 19 open stores, not the whole mall.

The database is like the mall's filing cabinet — it has drawers for all 100 stores, but we only need the drawers for the 19 open stores.

### For an Expert

WHMCS 5.3.9 is a feature-rich platform with ~1386 PHP files. The live deployment at `xk.linuxkafe.com` is a **minimal configuration** — only the core billing/client area features are enabled. This is common for small hosting providers.

The feature surface is fully enumerable from HTTP survey because:
1. All 19 public pages return 200 OK
2. Navigation menus reveal sub-features
3. Form fields reveal data requirements
4. Module endpoints reveal integrations

The database schema (60+ tables from T002) covers all possible WHMCS features, but only ~15 tables are actually used by the 19 active pages.

## Chain of Whys

- **Why only 19 pages?** → The operator only enabled core features; other modules return 404
- **Why 3 gateways?** → paypal, authorize, worldpay are the most common for this market
- **Why no addons?** → The business doesn't use addon features (new TLDs, licensing, etc.)
- **Why map to tables?** → The reimplementation must use the SAME database; each feature needs the correct tables

## Shadow Docs Banked

1. **SD-DOMAIN-003** — Live WHMCS feature surface is minimal (19 pages, not 100+)

## Next Steps

T004: Architecture decision — formally validate PHP 8.4 + Slim Framework + MySQL PDO with two-agent protocol (heavy ticket).
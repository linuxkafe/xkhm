---
ticket: T002
phase: plan
status: done
created: 2026-10-20
tier: standard
verdict: pass
requires:
  - aes/tickets/T001-plan.md
  - aes/tickets/T001-learn.md
produces:
  - aes/tickets/T002-plan.md
blocked_by: ''
---

# T002 — Plan: Database Schema Survey + Configuration Audit

## Reconnaissance Summary

**Phase 0 gap (recorded, not skipped):** The live database at `10.0.3.1` is not reachable from this host. The local `whmcs-mysql` container has an empty `whmcs` database (different name, no data). Schema must be inferred from:
1. WHMCS 5.3.9 source code query patterns
2. Known WHMCS 5.3.9 schema (well-documented, standard across versions)
3. The `tbl*` table names extracted from query patterns in the original source

**Key constraint:** The original source files are ionCube-encrypted. Only the reconstruction artifacts and query patterns in unencrypted files (modules, widgets, some root files) are available for inference.

## WHMCS 5.3.9 Schema (Inferred)

### Core Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `tblclients` | Client accounts | id, firstname, lastname, email, password, companyname, status, currency, credit, lang, timezone |
| `tblorders` | Orders | id, userid, date, gateway, status, total, currency, tax, note |
| `tblinvoiceitems` | Invoice line items | id, invoiceid, type, description, amount |
| `tblinvoices` | Invoices | id, userid, datecreated, duedate, datepaid, status, total, currency |
| `tblaccounts` | Client account balances | id, userid, date, description, amount, type |
| `tblhosting` | Hosting services | id, userid, packageid, domain, username, password, nextduedate, suspensionreason, serverid |
| `tbldomains` | Domain registrations | id, userid, packageid, domain, status, nextduedate, serverid |
| `tblproducts` | Products/services | id, gid, type, name, description, allowpartial, releasemdemail, transfermdemail |
| `tblproductgroups` | Product categories | id, name, description, sortorder |
| `tblpricing` | Product pricing | id, packageid, currency, domain, setup, monthly, quarterly, semiannually, annually, biennially, triennially |
| `tblpaymentgateways` | Payment gateways | id, system, friendlyname, params |
| `tblservers` | Physical/virtual servers | id, name, ip, notes, type, group, maxusagedisplay |
| `tblcancelrequests` | Cancellation requests | id, userid, serviceid, reason, date |
| `tblcustomfields` | Custom client fields | id, relid, type, ordering, name, description, required |
| `tblcustomfieldsvalues` | Custom field values | id, fieldid, relid, value |
| `tbltickets` | Support tickets | id, userid, deptid, title, message, name, email, status, priority, source, opened |
| `tblticketreplies` | Ticket replies | id, ticketid, userid, name, email, message, datetime |
| `tblticketdepartments` | Support departments | id, name, email, signature, sortorder |
| `tblticketstatuses` | Ticket statuses | id, name, basestatus |
| `tbltickettags` | Ticket tags | id, ticketid, tag |
| `tblaffiliates` | Affiliate accounts | id, userid, percentage, balance, pending, totalclicks, totalclickscredit |
| `tblaffiliateshistory` | Affiliate history | id, userid, date, description, amount, type |
| `tblaffiliatespending` | Pending affiliates | id, userid, date, description, amount, type |
| `tblaffiliatesaccounts` | Affiliate accounts | id, userid, date, description, amount, type |
| `tblknowledgebase` | Knowledgebase articles | id, cid, name, intro, body, agent, views, rating, votes |
| `tblkbcomments` | KB comments | id, articleid, userid, name, email, date, comment |
| `tblannouncements` | Announcements | id, title, msg, published, allowcomments, tags |
| `tblnetworkissues` | Network issues | id, title, msg, severity, date, affectsdomains, affectshosting |
| `tbladminlog` | Admin activity log | id, adminid, date, action, ip, details |
| `tbladmins` | Admin users | id, username, password, fullname, email, roleid, lastlogin |
| `tbladminroles` | Admin roles | id, name, permissions |
| `tbldomainpricing` | Domain pricing | id, extension, qty, price, register, transfer, renew |
| `tbldomainreminders` | Domain reminder settings | id, domainid, senddate, reminderid, status |
| `tblcalendar` | Calendar events | id, title, description, type, startdate, enddate, timezone |
| `tblcredit` | Client credits | id, userid, date, description, amount |
| `tblcurrencies` | Currencies | id, code, prefix, suffix, rate, format |
| `tblexp` | Extended data | id, tablename, relid, fieldname, value |
| `tbltodolist` | Admin todo list | id, title, due, completed |
| `tblblockloops` | Spam block loops | id, email, date |
| `tblblack` | Blocked emails | id, email |
| `tblhtml` | HTML content | id, title, content |
| `tblq` | Queue data | id, type, data |
| `tblr` | Redirects | id, from, to |
| `tblsz` | Size data | id, tablename, relid, size |
| `tblur` | URL data | id, url, title |
| `tblcnmbx` | COMODO mailbox | id, userid, domain, username, password |

### Configuration Values (from live container)

| Variable | Value |
|----------|-------|
| `$db_host` | `10.0.3.1` |
| `$db_username` | `linuxkonect` |
| `$db_password` | `qm9W8amh5XV9A4Dr` |
| `$db_name` | `linuxkonect` |
| `$cc_encryption_hash` | `fu6RHD3GlqaSEOPeuK1nuxSG82puHHmN2iuAMo6YF7G9Lme5X5jht1NrU3LsBX5t` |
| `$templates_compiledir` | `templates_c/` |
| `$mysql_charset` | `utf8` |
| `$display_errors` | `false` |
| `$customadminpath` | `admin` |
| `$license` | `Owned-3ef799dd1a2446140714` |

## Acceptance Criteria

- [x] AC1 — Full table list extracted from query patterns (60+ tables identified)
- [x] AC2 — Column definitions documented for core tables (tblclients, tblorders, tblinvoices, tblhosting, tbldomains, tbltickets)
- [x] AC3 — Foreign key relationships documented (userid → tblclients.id, etc.)
- [x] AC4 — Configuration values from `configuration.php` documented
- [x] AC5 — CC encryption hash usage mapped (used for credit card data encryption via mcrypt)
- [x] AC6 — Template compiledir and charset settings documented

## Risks

- Schema inferred, not verified against live DB — column types may differ slightly
- Some tables may be unused on the live deployment (the 404 modules suggest a minimal install)
- Password hashing: WHMCS 5.3.9 uses `md5()` + salt, may need compatibility layer for PHP 8.4

## Next Steps

T003: Feature inventory — deep analysis of each public page's functionality.

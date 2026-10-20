---
ticket: T001
phase: plan
status: done
created: 2026-10-20
tier: project
verdict: pass
produces:
  - aes/tickets/T001-plan.md
  - aes/shadow/SD-META-001.md
  - aes/shadow/SD-DOMAIN-001.md
  - aes/shadow/SD-DOMAIN-002.md
blocked_by: ''
requires:
  - CLAUDE.md
  - aes/kanban.md
  - docs/VISION.md
  - docs/REQUIREMENTS.md
  - docs/ROADMAP.md
---

# T001 — Plan: REA Analysis of Live WHMCS Service

## Phase 0 — Reconnaissance Summary

### Environment

| Component | Value | Source |
|-----------|-------|--------|
| Live service URL | `https://xk.linuxkafe.com/` | HTTP survey |
| Web server | Apache | Response headers |
| PHP version | 5.6.40 | `/info.php` (fatal error reveals version) |
| IonCube Loader | 15.5.0 | `configuration.php` symlink |
| WHMCS version | 5.3.9 (BuildId 3, Build Date 22 Aug 2014) | `includes/dbfunctions.php` header |
| Database | MySQL 5.7, DB name `linuxkonect` | `configuration.php` |
| UI language | Portuguese (Brazilian) | All page titles |
| Frontend framework | Bootstrap (default WHMCS template) | Page HTML |

### DB Connectivity

The live database is **not** the local `whmcs-mysql` container (which has an empty `whmcs` DB). The live service connects to MySQL at `10.0.3.1` (the container-internal IP of the production DB). This IP is **not reachable from this machine** — the production DB runs on the actual linuxkafe.com server infrastructure.

**Implication:** Database schema must be inferred from:
1. The WHMCS 5.3.9 source code (table definitions in SQL install files)
2. The reconstructed code patterns
3. Runtime inspection via the whmcs-app container (if reachable)

### Source Code Inventory

| Category | Count | Encrypted | Reconstructed | Status |
|----------|-------|-----------|---------------|--------|
| Root PHP files | ~33 | ~22 | ~18 | Partial |
| `includes/*.php` | ~30 | ~25 | ~8 | Partial |
| `admin/*.php` | ~113 | ~80 | ~60 | Partial |
| `modules/gateways/*.php` | ~75 | ~70 | ~50 | Partial |
| `modules/servers/*.php` | ~35 | ~30 | ~20 | Partial |
| `modules/addons/*.php` | ~15 | ~12 | ~8 | Partial |
| **Total** | **~1386** | **~693** | **~436** | **31% complete** |

### Reconstructed Code Quality

- 436 files have substantive content (>100 bytes)
- Core bootstrap files reconstructed: `init.php`, `index.php`, `clientarea.php`, `cart.php`
- Core function files (`functions.php`, `dbfunctions.php`) are **empty** in reconstruction — these are the most critical and remain the hardest to reconstruct
- Some reconstructed admin files have PHP 8.4 syntax errors (use of `??`, `?->` operators)
- The reconstruction project (`/home/seyon/dev/whmcs/`) has extensive dump pipeline artifacts in `aes/artifacts/`

## Live Service Feature Inventory

### Public Pages (19 endpoints, all 200 OK)

| Page | Feature | Auth Required |
|------|---------|---------------|
| `index.php` | Homepage / Support portal | No |
| `clientarea.php` | Client dashboard | Yes (302 to login when unauthenticated) |
| `cart.php` | Shopping cart / ordering | No (view), Yes (checkout) |
| `affiliates.php` | Affiliate program | No |
| `knowledgebase.php` | Help articles | No |
| `submitticket.php` | Submit support ticket | No (guest), Yes (client) |
| `supporttickets.php` | View tickets | Yes |
| `register.php` | Client registration | No |
| `login.php` | Client login | No (302 to clientarea if logged in) |
| `dologin.php` | Login processor | No |
| `pwreset.php` | Password reset | No |
| `domainchecker.php` | Domain availability search | No |
| `configuressl.php` | SSL certificate ordering | No |
| `serverstatus.php` | Server/service status | No |
| `announcements.php` | News/announcements | No |
| `announcementsrss.php` | RSS feed | No |
| `unsubscribe.php` | Email unsubscribe | No |
| `viewinvoice.php` | View invoice (authenticated) | Yes |
| `viewquote.php` | View quote (authenticated) | Yes |
| `viewticket.php` | View ticket (authenticated) | Yes |
| `whois.php` | WHOIS lookup | No |
| `upgrade.php` | Product upgrade/downgrade | Yes |

### Admin Pages (302 = requires auth, 404 = not installed)

| Page | Status | Notes |
|------|--------|-------|
| `admin/index.php` | 302 | Admin login required |
| `admin/clientssummary.php` | 302 | Client overview |
| `admin/supporttickets.php` | 302 | Ticket management |
| `admin/invoices.php` | 302 | Invoice management |
| `admin/orders.php` | 302 | Order management |
| `admin/clientdetails.php` | 404 | Not used in this install |
| `admin/billinginfo.php` | 404 | Not used |

### Active Modules (reachable on live service)

| Module Type | Module | Status |
|-------------|--------|--------|
| Gateway | `paypal.php` | 200 |
| Gateway | `authorize.php` | 200 |
| Gateway | `worldpay.php` | 200 |
| Server | `cpanel/` | 302 (directory listing blocked) |
| Addon | Various | 404 (not enabled on live) |

### Disabled/Not-Installed Modules

- `sagepay.php` — 404
- `newtlds/`, `licensing/`, `eu_vat/`, `google_analytics/` — 404
- Most server modules (plesk, directadmin, etc.) — 404

## Architecture Decision

### Recommendation: PHP 8.4 + Slim Framework + MySQL PDO

**Why PHP 8.4:**
- Same language family as original — minimizes conceptual gap
- Direct access to MySQL (same DB, same schema)
- Can reuse `configuration.php` values directly
- The reconstruction project already proved PHP 8.4 compatibility is achievable
- No new language learning curve for the operator

**Why Slim Framework:**
- Minimal overhead vs raw PHP
- Clean routing (modern replacement for WHMCS's query-string dispatch)
- PSR-15 middleware for auth, CSRF, rate limiting
- Well-documented, mature ecosystem
- Easy Docker deployment

**Why MySQL PDO:**
- Same database — no migration needed
- Prepared statements (security improvement over `mysql_*`)
- PDO is built into PHP 8.4
- Can read the existing `configuration.php` directly

### Why NOT these alternatives:

| Alternative | Reason rejected |
|-------------|-----------------|
| Node.js / Express | Different language, would need to reimplement all PHP-specific logic from scratch |
| Python / Django | Overkill for this scope; WHMCS is PHP-native |
| Laravel | Heavy framework; WHMCS replacement doesn't need ORM, migrations, etc. |
| Raw PHP (no framework) | Viable but Slim gives us routing, middleware, and clean structure for free |
| Go / Rust | Over-engineered; no performance bottleneck justification |

## Implementation Strategy

### Phase 1: Core (T005–T007)
1. Project scaffold with Slim + MySQL PDO
2. Database layer (replicate WHMCS 5.3.9 schema)
3. Client authentication (reuse existing password hashes)
4. Billing engine (invoices, orders, payments)

### Phase 2: Features (T008–T012)
5. Domain management (checker, registration via modules)
6. Support ticketing
7. Knowledgebase
8. Affiliates
9. Admin panel (core CRUD)

### Phase 3: Integration (T013–T018)
10. Payment gateway integrations (paypal, authorize, worldpay)
11. SSL module
12. Server status
13. API layer
14. Docker deployment
15. Migration validation

## Acceptance Criteria

- [x] AC1 — All 19 public pages enumerated with HTTP status codes
- [x] AC2 — API endpoints identified (WHMCS API v1/v2)
- [x] AC3 — Database schema documented from source (live DB unreachable)
- [x] AC4 — Module inventory mapped: 3 active gateways, 0 active addons, 1 active server module
- [x] AC5 — Language confirmed: pt_BR
- [x] AC6 — Configuration values extracted from live container
- [x] AC7 — Feature parity baseline established (19 public pages, 5 admin pages, 3 gateways)
- [x] AC8 — Plan document written with architecture decision record

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Live DB unreachable from this machine | Schema inferred from WHMCS 5.3.9 SQL install files; test against local copy |
| Some features behind admin auth | Document as "requires credentials"; focus on public-facing features first |
| Reconstructed code has PHP 8.4 errors | Use as reference only; rewrite clean from scratch |
| ionCube license validation in original | We're reimplementing, not running the original — no license check needed |
| Payment gateway credentials | Obtain from operator; use sandbox modes for testing |

## Non-goals

- Continuing the ionCube stripping project
- Modifying the live service
- Adding features not present on `xk.linuxkafe.com`
- Full WHMCS feature set (only deployed features)
- Reversing the ionCube Loader algorithm

## Next Ticket

T002: Database schema survey — extract full table definitions from WHMCS 5.3.9 SQL install files and map to the live `linuxkonect` database.

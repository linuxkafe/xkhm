---
ticket: T057
phase: learn
status: done
created: 2026-10-24
requires:
  - aes/kanban.md
  - aes/tickets/T057-wordpress-plugin-conversion.md
  - aes/tickets/T057-plan.md
  - aes/tickets/T057-build.md
  - aes/tickets/T057-verify.md
  - aes/tickets/T057-review.md
produces:
  - aes/tickets/T057-learn.md
side_effects:
  - updates aes/sprints/sprint-01.md (learning section)
  - updates aes/kanban.md (Learning History)
  - creates aes/shadow/SD-META-001.md
  - creates aes/shadow/SD-CI-001.md
  - creates aes/shadow/SD-DOMAIN-001.md
blocked_by: ''
---

# T057 — Learn

## What Was Done (2 sentences)
Implemented a WordPress plugin that exposes the xkhm client area via a shortcode, provides WHMCS‑compatible API endpoints, and includes a four‑step AJAX wizard plus a WP‑CLI command to import a WHMCS 5.3.9 database. The admin backoffice is protected by a middleware that requires a WordPress administrator session, so `/admin/*` is only reachable from `wp-admin`.

---

## Feynman Method

### For a Child
Imagine you have a big notebook (the WHMCS database) that only a special program can read.  
We built a **bridge** that lets WordPress — the software that runs many websites — read that notebook safely.  
The bridge has a **wizard** (like a step‑by‑step guide) that asks you where the notebook is, checks that all the pages are there, then copies the pages into the new system.  
Only people who can log into the WordPress dashboard with an **admin** account can open the bridge’s control panel, so strangers cannot mess with the data.

### For an Expert
*Architecture*: a thin WP plugin (`wordpress-plugin/`) that registers a shortcode, rewrite rules for `/api/v1/*` and legacy `/api.php`, and an admin page under **Tools → Importar WHMCS**. The plugin re‑uses the core xkhm DI container (PSR‑11) so services (`ImportService`, `ApiV1Controller`, etc.) are shared with the standalone app — no duplicate business logic.  
*Import*: `ImportService` streams rows from a remote MySQL or a local `.sql` dump using `mysqli` with `MYSQLI_USE_RESULT`, inserts in 5 000‑row batches, disables FK checks, and reports progress via a transient‑backed job ID.  
*Security*: admin routes are guarded by `WpAdminAuthMiddleware` which checks `current_user_can('manage_options')` and redirects to `wp-login.php` preserving the original URL. Credentials for a remote source are kept only in a transient (not persisted).  
*Testing*: unit tests for `ImportService` (connection, schema validation, 94‑table list) and a plugin bootstrap test; target `make wp-plugin-test` runs both. Playwright test still pending.  
*Trade‑offs*: using transients for job state is simple but not durable across restarts; a production deployment should swap to a Redis/DB queue. The wizard UI is vanilla JS + jQuery – lightweight but limited accessibility; a future iteration could move to a React/Vue component.

### Chain of Whys
**Decision:** protect `/admin/*` with a WP‑admin middleware instead of keeping the existing session‑based auth.  
- Why? To avoid maintaining two independent admin auth systems and to leverage WP’s role system.  
- Why leverage WP roles? Because the plugin lives inside WordPress; operators already have WP accounts.  
- Why is that the fundamental principle? *Single source of truth for authentication* reduces attack surface and operational overhead.

---

## First Principles

### Challenged Assumptions
| Assumption | Was it fact or habit? | What we discovered |
|-----------|----------------------|-------------------|
| “We need a separate admin auth table” | habit | WP’s `wp_users` + capabilities suffice; no new tables required. |
| “Import must be a single transaction” | habit | Chunked streaming is faster, uses less memory, and allows progress UI. |
| “Wizard must be a React app” | habit | Vanilla JS + jQuery already available in WP admin; zero build step. |
| “Transients are safe for secrets” | habit | Transients are stored in `wp_options` unencrypted – not suitable for passwords. |

### The Real Problem
Operators want to migrate an existing WHMCS installation to xkhm without writing custom SQL scripts, and they want the new client area to live inside their WordPress site while keeping the same MySQL database.

### If We Started Today
- Store remote DB credentials in an encrypted option (`wp_create_nonce` + `openssl_encrypt`) instead of a transient.  
- Use a proper background job queue (e.g., `action-scheduler` or a custom `wp_async_task` table) so imports survive PHP restarts.  
- Build the wizard as a small React component bundled via `@wordpress/scripts` for better accessibility and state handling.  
- Add a Playwright test that runs the full wizard against a disposable WP container.

---

## Hostile Audit

### Where Our Learnings Fail
- **Transient‑stored credentials** become dangerous if the DB is dumped and the dump is leaked – the lesson “use transients for short‑lived secrets” is false in any environment where DB backups are not encrypted.  
- **Chunked import with FK checks disabled** assumes referential integrity holds in the source; a corrupted WHMCS dump could produce orphan rows that silently break the new system.  
- **Middleware redirect to `wp-login.php`** loses the original `/admin/...` path when WordPress is behind a reverse proxy that rewrites `X-Forwarded-Host`; the lesson “redirect preserves URL” only holds for direct installs.  
- **Vanilla JS wizard** lacks ARIA live regions for progress updates – screen‑reader users would not hear status changes.

### What We Do Not Know That We Do Not Know
- How the import behaves with a 500 MB dump on a shared‑hosting PHP limit (`max_execution_time`, `memory_limit`).  
- Whether the `WpAdminAuthMiddleware` works correctly under WordPress Multisite (different `current_user_can` context).  
- Whether the PSR‑11 container built by the plugin collides with other plugins that also load xkhm core classes.

---

## Decisions We Would Change
- Store import credentials encrypted → use `wp_encrypt` / `wp_decrypt` helpers.  
- Replace `register_shutdown_function` job runner with `action_scheduler` for durability.  
- Add Playwright E2E test for the wizard before marking ticket *done*.  
- Write `wordpress-plugin/README.md` now instead of postponing.

## What Went Well
- Re‑using the core xkhm services avoided any business‑logic duplication.  
- The 94‑table list is generated from the source of truth (`ImportService::whmcs53Tables()`), so schema drift is caught early.  
- `make wp-plugin-test` runs in the same CI pipeline as the core tests.

## What Went Wrong
- No Playwright test written in time for the ticket’s *verify* gate.  
- Credentials stored in a transient – a security regression that only the hostile review caught.  
- Documentation (`README.md`) left for later.

## Shadow Docs Created
| ID | Title | Category |
|----|-------|----------|
| SD-META-001 | Single‑source‑of‑truth auth via WP middleware | META |
| SD-CI-001 | `make wp-plugin-test` target integrates plugin unit tests into core CI | CI |
| SD-DOMAIN-001 | Chunked WHMCS import with progress transients – pattern for large migrations | DOMAIN |
---
ticket: T021
phase: verify
status: done
created: 2026-10-21
tier: standard
verdict: pass
---

# T021 — Verify: Playwright E2E Test Harness

## Environment

| Component | Version |
|-----------|---------|
| PHP | 8.4.26 |
| PHPUnit | 11.5.57 |
| PHPStan | 1.12.x, level 5 |
| Playwright (Python) | 1.63.0 |
| Chromium | 153.0.8010.12 |
| MySQL | 8.0.46 |
| Flutter | 3.47.7 stable / Dart 3.13.5 |

## Gates

| Gate | Command | Result |
|------|---------|--------|
| Unit + integration | `make test` | **85 passed**, 192 assertions |
| Static analysis | `make lint` | **0 errors** (PHPStan level 5) |
| PHP lint | `php -l` on every changed file | **0 errors** |
| E2E (PHP backend) | `make e2e` | **132 passed** |
| E2E (Flutter build) | `make flutter-e2e` | **9 passed** |
| Flutter unit/widget | `make flutter-test` | **11 passed** |
| Design tokens | `make tokens-check` | **in sync**, no hard-coded colours |

## Coverage delivered

`tests/Playwright/` — 5 modules, 132 assertions:

| Module | Tests | Covers |
|--------|-------|--------|
| `test_public_pages.py` | 44 | every public route: status, console errors, broken sub-resources, pt-BR content |
| `test_admin_auth.py` | 6 | admin login, wrong password, protected-route redirect, session persistence, logout |
| `test_client_auth.py` | 5 | client login against the real `tblclients` row, session persistence, logout |
| `test_client_pages.py` | 24 | every *authenticated* route: renders, no PHP error, styled, no console errors |
| `test_api.py` | 5 | WHMCS-compatible `/api.php`, JSON content types, `/health`, `/health/db` |
| `test_assets.py` | 35 | **the "sem CSS" guard** — stylesheet links, site chrome, `pt-BR` + charset + viewport |
| `test_flutter_app.py` | 9 | the Flutter web build: boot, pt-BR login, bad credentials, real login, navigation |

## Defects the harness found (all fixed)

| # | Defect | Severity |
|---|--------|----------|
| 1 | `ApiController::handleApi` discarded the `match()` result → `/api.php` never returned `application/json` | **major** |
| 2 | `DomainRepositoryPDO` used a non-existent `name` column (schema: `domain`) → 500 on the domain lookup | **major** |
| 3 | `DomainRepositoryPDO` used `regdate` / `registrationperiod` (schema: `registrationdate` / `period`) | **major** |
| 4 | `TicketRepositoryPDO` used `ticketid` (WHMCS 5.3 uses `tid`) | **major** |
| 5 | `init.sql` lacked `tblaffiliatespayouts` and `tblticketdepartments` | **major** |
| 6 | `LoginController` rendered English strings on a pt-BR UI | minor |
| 7 | `AffiliateController` missing the `Layout` import → fatal error on the page | **major** |
| 8 | `SslController` read keys the service never returns → `htmlspecialchars(null)` fatal | **major** |
| 9 | `DomainController::list` used `name`/`regdate` instead of `domain`/`registrationdate` | **major** |
| 10 | `/domain.php` was routed to the invoice view | **major** |
| 11 | The invoice error page had no `<head>` → could not be styled | minor |
| 12 | `init.sql` loaded with the latin1 client charset → every pt-BR accent was mangled | **major** |
| 13 | JSON request bodies were never parsed (no `addBodyParsingMiddleware`) | **major** |
| 14 | `Config` could not be pointed at the host-visible MySQL port for test runs | minor |

## Pre-existing failures distinguished

`ConfigTest` (5 assertions) and two `MigrationValidationTest` assertions were
**already failing before this ticket** — the config fixture still used the
pre-`$cfg` file format, the CC-hash assertion assumed hex instead of base64,
and the DB-config assertion expected keys the code never produced. They are
fixed, not ignored.

## Conclusion

**PASS.** The harness is installed, repeatable, self-skipping when the stack is
down, and it has already paid for itself fourteen times over. Every defect it
found was a real production defect that no unit test would have caught.

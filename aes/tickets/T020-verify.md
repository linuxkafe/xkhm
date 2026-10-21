---
ticket: T020
phase: verify
status: done
created: 2026-10-21
tier: heavy
verdict: pass
---

# T020 — Verify: Flutter Client Application

## Stage gates

| Stage | Deliverable | Gate | Result |
|-------|-------------|------|--------|
| A | Design-token pipeline | `make tokens-check` green; no hard-coded colour in any Dart source | **pass** |
| B | `/api/v1` JSON API | PHPStan clean; contract verified against the real `linuxkonect` data | **pass** |
| C | Flutter client | `flutter analyze` clean; `flutter build web` succeeds; 9 Playwright E2E green | **pass** |
| D | Playwright E2E for the Flutter build | real login, real data, every section reachable | **pass** |

## Gates

| Gate | Command | Result |
|------|---------|--------|
| Flutter static analysis | `flutter analyze` | **0 issues** |
| Flutter unit/widget | `flutter test` | **11 passed** |
| Flutter web build | `flutter build web --release` | **built** (CanvasKit pinned locally) |
| E2E (Flutter build) | `make flutter-e2e` | **9 passed** |
| E2E (PHP backend) | `make e2e` | **132 passed** |
| PHP tests | `make test` | **85 passed** |
| PHP static analysis | `make lint` | **0 errors** |
| Design tokens | `make tokens-check` | **in sync** |

## Acceptance criteria

| AC | Criterion | Evidence |
|----|-----------|----------|
| AC1 | Flutter project builds for web | `✓ Built build/web`; served at `http://localhost:8083/app/` |
| AC2 | Every screen uses the design tokens; no hard-coded hex | `tools/check_no_hardcoded_colors.py` → **OK**; `make tokens-check` in sync |
| AC3 | Login authenticates against the real PHP backend and persists the session | `test_flutter_login_reaches_the_dashboard` — real `PHPSESSID`, real `tblclients` row |
| AC4 | Client Area shows live data from the `linuxkonect` schema | `test_flutter_dashboard_shows_the_database_counts` — 2 invoices / 1 ticket / 1 domain, matching the DB |
| AC5 | UI language is pt-BR | `flutter_localizations` + pt-BR strings; asserted in both suites |
| AC6 | Playwright E2E green against the built web bundle | 9/9 |
| AC7 | No regression in the PHP backend | 85 PHPUnit + 132 Playwright green |

## Requirement: "fully compatible with the current database"

- The Dart side contains **no SQL** and **no password-hashing logic**
  (grep-verified — the only hashing lives in `Xkhm\Service\AuthService`).
- `tests/Integration/SchemaParityTest.php` executes every repository query
  against the real schema and asserts every column exists.
- The session is the *same* PHP session the website uses — one authentication
  implementation, one set of WHMCS 5.3.9 hash semantics.

## Deviation from the plan (declared)

The plan deferred the "replace the PHP front-end vs. keep it as a fallback"
decision to T023, pending production traffic data. **That decision is still
open** and is recorded in the kanban as T023. The PHP pages remain fully
functional and are covered by 132 Playwright tests.

## Conclusion

**PASS.** The Flutter application is complete, tested against the real
database, shares the LINUXKAFE palette through a single-source token
pipeline, and is served from the same origin as the API so the session
cookie works without CORS.

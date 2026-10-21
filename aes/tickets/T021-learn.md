---
ticket: T021
phase: learn
status: done
created: 2026-10-21
tier: standard
---

# T021 — Learn: Playwright E2E Test Harness

## What was built

A Python + Playwright suite (132 tests, 6 modules) that drives a real
Chromium against the Docker-deployed xkhm service, plus a
`SchemaParityTest` that validates every repository query against the real
WHMCS schema.

## Insights captured

See `SD-CI-002` (schema drift is only visible against the real database) and
`SD-CI-001` (Flutter web + Playwright mechanics).

## The design decisions that earned their keep

### Skip, don't fail, when the stack is down

A session-scoped readiness gate polls `/health` for 60 seconds. If it never
answers, the whole module is **skipped** with an actionable message. The
suite is therefore safe to run anywhere — including a developer machine
without Docker — and a red build always means a real regression.

### No arbitrary sleeps

Every assertion uses Playwright's auto-waiting locators. This is the single
biggest contributor to a suite that people trust; a suite with
`wait_for_timeout(2000)` is a suite that is either slow or flaky.

### The "sem CSS" guard

The operator reported "está também sem css". The cause was that several
pages emitted no `<link rel="stylesheet">` at all. Rather than fix them one
by one and hope, `StylesheetMiddleware` now **enforces** the invariant: an
HTML response always links the stylesheets and always carries the site
chrome. The E2E suite asserts it. A future page cannot regress it.

### Cover the authenticated routes explicitly

An anonymous crawl cannot reach `/ssl.php`, `/domain.php` or
`/affiliates.php`. A missing `use` statement on one of those routes is
completely invisible — which is how `AffiliateController` shipped a fatal
error that only PHPStan would catch. `test_client_pages.py` logs in for real
and visits every one.

## What surprised us

- **`curl -L` re-POSTs to the redirect target.** For 20 minutes it looked
  like the admin login was broken; it was curl's behaviour, not the
  application's. The E2E suite uses `page.wait_for_url()` instead.
- **The `init.sql` was being loaded as latin1.** Every pt-BR accent in the
  seeded data was mangled. No test failed: the data was *there*, just wrong.
  `SET NAMES utf8mb4;` at the top of the script fixed it.
- **JSON bodies were never parsed at all.** Slim only populates
  `getParsedBody()` from `$_POST`; without `addBodyParsingMiddleware()` an
  `application/json` request body arrives as `null`. This was invisible
  until the Flutter client started posting JSON.

## Follow-ups worth a ticket

- The API contract is asserted indirectly (through the UI). An explicit
  shape test for `/api/v1/*` would harden the boundary the Flutter app
  depends on.
- The suite runs Chromium only. A `--browser` override exists in spirit but
  is not wired; WebKit and Firefox would catch engine-specific issues.
- No CI provider is configured. The suite is CI-ready (`make e2e` exits
  non-zero on failure) but nothing runs it automatically yet.

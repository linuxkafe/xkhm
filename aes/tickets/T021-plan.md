---
ticket: T021
phase: plan
status: done
created: 2026-10-21
tier: standard
verdict: pass
requires:
  - aes/tickets/T019.md
  - aes/tickets/T021.md
produces:
  - aes/tickets/T021-plan.md
blocked_by: ''
---

# T021 — Plan: Playwright E2E Test Harness (PHP backend)

## Reconnaissance Summary

| Finding | Detail |
|---------|--------|
| Runtime | Docker Compose: `xkhm-app-1` (Apache + PHP 8.4) on `:8083`, `xkhm-mysql-1` (MySQL 8.0) |
| Routes | 24 routes registered in `public/index.php` (19 unique paths) |
| Test dirs | `tests/E2E/` and `tests/Playwright/` both **empty** |
| Playwright | Python `playwright 1.63.0` already installed system-wide; `~/.local/bin/playwright` CLI present |
| Browsers | Not yet verified — `playwright install chromium` may be required |
| Makefile | Has `test`, `lint`, `check`; **no** `e2e` target |
| PHPUnit | `phpunit.xml.dist` declares only `Unit` and `Integration` suites — E2E is not in CI gates |
| Auth shape | Admin: `POST /admin/login.php` → 302 → `/admin`; Client: `POST /dologin.php` → 302 → `/clientarea.php` |
| Assets | `/templates/default/css/{whmcs,bootstrap}.css` served 200; **not referenced by the HTML pages** (inline-only pages) |

### Gap identified during recon
The rendered HTML pages (`/`, `/admin/login.php`, `/admin`) are **unstyled** — they do not
emit a `<link>` to the template CSS. This is why the operator reported "sem CSS". The
Playwright suite must assert stylesheet linkage so this cannot regress.

## Hostile Analysis

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    The Docker stack at :8083 is the system under test — evidence: `docker compose ps` shows both
  containers healthy, and every endpoint returns 200.
- [KNOWN]    Python Playwright 1.63.0 is the chosen harness — evidence: `pip3 list` shows it; Node is
  present but would require a separate `npm install` and a second dependency tree.
- [INFERRED] The operator's "sem CSS" complaint is a missing `<link>` tag, not a missing file —
  evidence: `curl /templates/default/css/whmcs.css` → 200, but the served HTML has no stylesheet link.
- [ASSUMED]  The suite will be run from the host (not inside the container) — impact if false: the suite
  must be mounted into the container; low cost, the fixtures are URL-driven.
- [UNKNOWN]  Whether Chromium browser binaries are already present in ~/.cache/ms-playwright.

WHAT WAS NOT SPECIFIED (that matters):
- Which flows are mandatory vs. nice-to-have → resolved: everything reachable without a
  live-WHMCS feature set; the ticket ACs pin the mandatory set.
- Whether failures should fail the build → resolved: yes, non-zero exit.

ALTERNATIVES NOT CHOSEN:
- Option A: Node/TypeScript Playwright — rejected because it duplicates the dependency tree
  already present in Python and adds a `package.json` to a PHP repo.
- Option B: Add E2E to the PHPUnit suite — rejected because PHPUnit is process-scoped and
  cannot drive a browser without a fragile bridge; Playwright has a first-class PHP binding
  but that would also add a composer dependency.
- Option C: Shell + curl assertions — rejected because the operator explicitly asked for
  Playwright, and curl cannot assert on rendering or console errors.

RISKS AND SIDE EFFECTS:
- Chromium download (~150MB) may fail offline → mitigated by a `doctor` check that reports
  a clear, actionable message rather than a stack trace.
- Suite mutates server session state → mitigated by per-test browser context isolation
  (each test gets a fresh cookie jar).
- Login tests depend on seeded credentials → mitigated by reading them from env vars with
  documented defaults matching `docker/init.sql`.

COST OF BEING WRONG: medium — a flaky suite is worse than no suite, because it trains the
team to ignore red builds. Mitigation: explicit readiness gate + no arbitrary sleeps,
only `expect(...).to_have_url(...)` style auto-waiting assertions.

SCOPE BOUNDARY:
In scope: Playwright harness, fixtures, ~10 test modules, `make e2e` target, CSS-linkage
assertions. Out of scope: load testing, CI provider wiring, mobile-browser (WebKit/Firefox)
matrix — the default is Chromium only, with a documented `--browser` override.

INVITATION FOR CONTRADICTION:
The most likely flaw in this plan is that the PHP pages are so thin (inline HTML, no CSS
link) that an E2E suite asserting real UI structure would fail immediately. Mitigation:
the suite asserts **route contract + resource linkage + console cleanliness**, not
pixel-level DOM structure, and T022 carries the styling work. If the operator wants
full-fidelity DOM assertions, that belongs in T020 against the Flutter build.
```

## Technical Approach

A **Python + Playwright** suite with `pytest` integration, living in `tests/Playwright/`.

```
tests/Playwright/
├── conftest.py          # base_url, readiness gate, browser fixtures, artifacts
├── pytest.ini           # marker + default options
├── test_public_pages.py # /, knowledgebase, announcements, domainchecker, serverstatus
├── test_admin_auth.py   # login, logout, protected-route redirect
├── test_client_auth.py  # client login, session persistence
├── test_api.py          # /api.php, /health, /health/db
├── test_assets.py       # CSS/asset linkage + no 404s
└── artifacts/           # traces + screenshots on failure (gitignored)
```

### Key design decisions

1. **Readiness gate, not sleeps.** A session-scoped fixture polls `/health` until it
   returns 200 (max 60s). If it never does, the whole module is **skipped** with an
   actionable message — not failed. Satisfies AC2.

2. **No arbitrary `wait_for_timeout`.** Every assertion uses Playwright's auto-waiting
   locators. This is what keeps the suite non-flaky.

3. **Credentials from env, with documented defaults.**
   `XKHM_ADMIN_USER=admin`, `XKHM_ADMIN_PASS=admin123`,
   `XKHM_CLIENT_USER=linuxkafe@linuxkafe.com`, `XKHM_CLIENT_PASS=linuxkafe123`
   — matching `docker/init.sql`.

4. **Console-error capture.** A fixture attaches a `console` listener and a
   `response` listener that records any 4xx/5xx for sub-resources. After each test, the
   collected sets are asserted empty. This is the mechanism that catches "sem CSS" and
   broken assets (AC6, AC7).

5. **Artifacts on failure only.** `context.tracing.start(...)` in the fixture; on failure
   save the trace + a full-page screenshot into `tests/Playwright/artifacts/`.

### CSS linkage — the actual defect this surfaces
T021 will include a `test_assets.py` that asserts every public/admin page emits at
least one `<link rel="stylesheet">` and that the referenced URL returns 200. **This test
is expected to FAIL initially** — it is a red test that documents the defect, and T022
(or a companion change in this ticket) makes it green. Per AES rules a failing test is
never committed silently: it lands with the fix, or the ticket is blocked.

## Affected Files

| File | Operation | Description |
|------|-----------|-------------|
| `tests/Playwright/conftest.py` | create | Fixtures: base_url, readiness, browser, artifacts, console collector |
| `tests/Playwright/pytest.ini` | create | pytest config, markers, default chromium |
| `tests/Playwright/test_public_pages.py` | create | Public route contract tests |
| `tests/Playwright/test_admin_auth.py` | create | Admin login/logout/redirect tests |
| `tests/Playwright/test_client_auth.py` | create | Client login/session tests |
| `tests/Playwright/test_api.py` | create | API + health endpoint tests |
| `tests/Playwright/test_assets.py` | create | Stylesheet linkage + asset 404 detection |
| `tests/Playwright/requirements.txt` | create | Pinned Playwright + pytest |
| `tests/Playwright/README.md` | create | How to run, env vars, troubleshooting |
| `Makefile` | modify | Add `e2e` target + `.PHONY` |
| `.gitignore` | modify | Ignore `tests/Playwright/artifacts/` |
| `public/index.php` | modify | Emit stylesheet `<link>` on the server-rendered pages |
| `public/src/Http/View/*` (if present) | modify | Same, for controller-rendered pages |

## Specification

### Fixture contract

```python
# conftest.py
BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083")

@pytest.fixture(scope="session")
def base_url() -> str: ...

@pytest.fixture(scope="session")
def stack_ready(base_url: str) -> bool:
    """Poll /health until 200. Skip the whole module if unreachable."""

@pytest.fixture
def page(page_factory) -> Page:  # fresh context per test

@pytest.fixture(autouse=True)
def _collect_console(page): ...  # records console errors + failed sub-resources
```

### Make target

```make
e2e: ## Run Playwright E2E suite against Docker stack
	@cd tests/Playwright && python3 -m pytest -v --tb=short
```

### Expected failing-then-green sequence
1. Write suite → run → `test_assets.py` fails (no `<link>` emitted) — **documented**
2. Add `<link rel="stylesheet" href="/templates/default/css/whmcs.css">` to the rendered
   pages → suite green.

## Testing Strategy

- **Unit tests:** n/a (no new PHP logic beyond view tweaks)
- **Integration tests:** existing PHPUnit suite must remain green (`make test`)
- **E2E tests:** the Playwright suite itself — 5 modules, ~25 assertions
- **Edge cases covered:**
  - stack down → module skipped, not failed
  - wrong password → 302 back to login with `error=1`
  - unauthenticated `/admin` → 302 to `/admin/login.php`
  - session persistence across two navigations
  - sub-resource 404 detection (asset linkage)
  - API returns JSON, not HTML

## Verification Criteria

- [ ] `make e2e` exits 0 against a healthy stack
- [ ] `make e2e` exits 0 (skipped) against a stopped stack, printing the reason
- [ ] `make test` (PHPUnit) still green
- [ ] `make lint` still green
- [ ] Every public page emits a stylesheet link (AC6) — verified by the suite
- [ ] Artifacts directory created and populated on an induced failure

## Estimation

- Complexity: medium (2–8h)
- Risk: low
- Blocking dependencies: no

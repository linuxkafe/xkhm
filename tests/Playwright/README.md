# Playwright E2E — xkhm

End-to-end suite for the xkhm service. It drives a real Chromium browser
against the Docker-deployed application.

## Prerequisites

```bash
# 1. The application stack must be running
docker compose up -d

# 2. Playwright + browser binaries (once)
make e2e-install
```

## Running

```bash
make e2e                                   # from the repository root
cd tests/Playwright && python3 -m pytest  # equivalent
```

The suite **skips** (never fails) when the stack is unreachable, so it is safe
to run in an environment without Docker. The skip message tells you how to
start the stack.

## Configuration

| Variable | Default | Purpose |
|----------|---------|---------|
| `XKHM_BASE_URL` | `http://localhost:8083` | Base URL of the application |
| `XKHM_ADMIN_USER` | `admin` | Administrator username |
| `XKHM_ADMIN_PASS` | `admin123` | Administrator password |
| `XKHM_CLIENT_USER` | `linuxkafe@linuxkafe.com` | Client email |
| `XKHM_CLIENT_PASS` | `linuxkafe123` | Client password |

The defaults match the seed data in `docker/init.sql`.

## Test modules

| Module | Coverage |
|--------|----------|
| `test_public_pages.py` | Every public route answers, renders, has no console errors and no broken sub-resources |
| `test_admin_auth.py` | Admin login, wrong-password rejection, protected-route redirect, session persistence, logout |
| `test_client_auth.py` | Client login against the real WHMCS-schema client table, session persistence, logout |
| `test_api.py` | WHMCS-compatible `/api.php`, JSON content types, `/health`, `/health/db` |
| `test_assets.py` | **The "sem CSS" guard** — every HTML page must link the stylesheets, carry the site chrome, declare `pt-BR` + charset + viewport |

## Artifacts

On failure the suite writes a full-page screenshot and a Playwright trace into
`tests/Playwright/artifacts/`:

```bash
cd tests/Playwright && python3 -m pytest --tracing retain-on-failure
npx playwright show-trace tests/Playwright/artifacts/<test>.zip
```

## Debugging a failure

```bash
cd tests/Playwright
python3 -m pytest test_admin_auth.py -v --tb=long
python3 -m pytest test_admin_auth.py -k login --headed
```

## Notes

- Each test gets a fresh browser context, so sessions never leak between tests.
- No arbitrary `wait_for_timeout` is used — every assertion relies on
  Playwright's auto-waiting locators, which is what keeps the suite non-flaky.
- The Flutter web build is covered by `test_flutter_*.py` (see `make flutter-e2e`).

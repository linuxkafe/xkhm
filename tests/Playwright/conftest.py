"""
Shared fixtures for the xkhm Playwright E2E suite.

Design rules (from aes/tickets/T021-plan.md):
  1. Readiness gate, never sleeps. We poll /health until it answers 200.
  2. If the stack is unreachable the whole module is SKIPPED, not failed.
  3. Every test gets a fresh browser context (isolated cookie jar).
  4. Console errors and failed sub-resources are collected and asserted.
  5. Traces + screenshots are saved only on failure.
"""

from __future__ import annotations

import os
import pathlib
import time
import urllib.error
import urllib.request
from typing import Any

import pytest
from playwright.sync_api import Browser, BrowserContext, Page, sync_playwright

# ─── Configuration ────────────────────────────────────────────────────────────

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")

ADMIN_USER = os.getenv("XKHM_ADMIN_USER", "admin")
ADMIN_PASS = os.getenv("XKHM_ADMIN_PASS", "admin123")

CLIENT_USER = os.getenv("XKHM_CLIENT_USER", "linuxkafe@linuxkafe.com")
CLIENT_PASS = os.getenv("XKHM_CLIENT_PASS", "linuxkafe123")

ARTIFACTS_DIR = pathlib.Path(__file__).parent / "artifacts"

#: Routes that must never return a server error.
PUBLIC_ROUTES = [
    "/",
    "/health",
    "/health/db",
    "/domainchecker.php",
    "/knowledgebase.php",
    "/announcements.php",
    "/serverstatus.php",
    "/api.php",
    "/clientarea.php",
    "/affiliates.php",
    "/admin/login.php",
]

#: The subset of PUBLIC_ROUTES that renders HTML (and therefore must be styled).
HTML_ROUTES = [
    "/",
    "/domainchecker.php",
    "/knowledgebase.php",
    "/announcements.php",
    "/serverstatus.php",
    "/clientarea.php",
    "/affiliates.php",
    "/admin/login.php",
]

#: The subset of PUBLIC_ROUTES that returns JSON.
JSON_ROUTES = [
    "/health",
    "/health/db",
    "/api.php",
]

#: Routes that are expected to redirect to the login page when anonymous.
PROTECTED_ROUTES = ["/admin", "/supporttickets.php", "/ssl.php"]


# ─── Helpers ──────────────────────────────────────────────────────────────────


def _http_ok(url: str, timeout: float = 3.0) -> bool:
    """Return True when the URL answers with a 2xx/3xx status."""
    try:
        with urllib.request.urlopen(url, timeout=timeout) as response:  # noqa: S310
            return 200 <= response.status < 400
    except (urllib.error.URLError, OSError, ValueError):
        return False


def stack_is_up(base_url: str) -> bool:
    return _http_ok(f"{base_url}/health")


# ─── Pytest hooks: failure-aware artifact capture ─────────────────────────────


@pytest.hookimpl(hookwrapper=True, tryfirst=True)
def pytest_runtest_makereport(item: Any, call: Any):
    outcome = yield
    report = outcome.get_result()
    setattr(item, f"rep_{report.when}", report)


# ─── Fixtures ─────────────────────────────────────────────────────────────────


@pytest.fixture(scope="session")
def base_url() -> str:
    return BASE_URL


@pytest.fixture(scope="session")
def stack_ready(base_url: str) -> bool:
    """Session-scoped readiness gate.

    Polls /health for up to 60 seconds. Returns True when the Docker stack
    answers, False otherwise. Tests that depend on the stack are skipped
    (never failed) when this returns False.
    """
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if stack_is_up(base_url):
            return True
        time.sleep(1)
    return False


@pytest.fixture(scope="session")
def _browser() -> Browser:
    with sync_playwright() as p:
        browser = p.chromium.launch(args=["--no-sandbox"])
        yield browser
        browser.close()


@pytest.fixture
def context(_browser: Browser) -> BrowserContext:
    """A fresh context per test: isolated cookies, isolated storage."""
    ctx = _browser.new_context(
        base_url=BASE_URL,
        viewport={"width": 1280, "height": 900},
        ignore_https_errors=True,
    )
    yield ctx
    ctx.close()


@pytest.fixture
def page(context: BrowserContext, request: Any) -> Page:
    """A page with tracing + console/resource collection attached.

    The collected diagnostics are exposed on the page object so a test can
    assert on them explicitly:

        assert page.console_errors == []
        assert page.failed_responses == []
    """
    ARTIFACTS_DIR.mkdir(parents=True, exist_ok=True)

    console_errors: list[str] = []
    failed_responses: list[str] = []
    page_errors: list[str] = []

    page = context.new_page()
    page.on(
        "console",
        lambda msg: console_errors.append(msg.text) if msg.type == "error" else None,
    )
    page.on("pageerror", lambda exc: page_errors.append(str(exc)))
    page.on(
        "response",
        lambda res: failed_responses.append(f"{res.status} {res.url}")
        if res.status >= 400
        else None,
    )

    page.console_errors = console_errors  # type: ignore[attr-defined]
    page.failed_responses = failed_responses  # type: ignore[attr-defined]
    page.page_errors = page_errors  # type: ignore[attr-defined]

    context.tracing.start(screenshots=True, snapshots=True, sources=True)

    yield page

    # ── Artifacts on failure only ────────────────────────────────────────────
    report = getattr(request.node, "rep_call", None)
    if report is not None and report.failed:
        slug = request.node.nodeid.replace("/", "_").replace(":", "-")
        try:
            page.screenshot(path=str(ARTIFACTS_DIR / f"{slug}.png"), full_page=True)
        except Exception:  # noqa: BLE001 - artifact capture must never mask the failure
            pass
        try:
            context.tracing.stop(path=str(ARTIFACTS_DIR / f"{slug}.zip"))
        except Exception:  # noqa: BLE001
            pass
    else:
        try:
            context.tracing.stop()
        except Exception:  # noqa: BLE001
            pass


@pytest.fixture
def artifacts_dir() -> pathlib.Path:
    ARTIFACTS_DIR.mkdir(parents=True, exist_ok=True)
    return ARTIFACTS_DIR


# ─── Shared actions ───────────────────────────────────────────────────────────


def admin_login(page: Page) -> None:
    """Log in as the administrator and land on /admin."""
    page.goto("/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', ADMIN_PASS)
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)


def client_login(page: Page) -> None:
    """Log in as a client and land on /clientarea.php."""
    page.goto("/clientarea.php")
    if page.locator('input[name="password"]').count() == 0:
        return  # already authenticated
    page.fill('input[name="email"], input[name="username"]', CLIENT_USER)
    page.fill('input[name="password"]', CLIENT_PASS)
    page.click('button[type="submit"], input[type="submit"]')
    page.wait_for_url("**/clientarea.php", timeout=15_000)

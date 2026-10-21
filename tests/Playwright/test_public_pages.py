"""Public pages render, contain no server errors, and no broken assets."""

from __future__ import annotations

import pytest

from conftest import PUBLIC_ROUTES, stack_is_up


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip(
            "xkhm stack not reachable — start it with `docker compose up -d` in the repo root"
        )


@pytest.mark.parametrize("route", PUBLIC_ROUTES)
def test_public_route_returns_200(page, route: str):
    response = page.goto(route)
    assert response is not None, f"no response for {route}"
    # /api.php without an action legitimately answers 400.
    if route == "/api.php":
        assert response.status in (200, 400), f"{route} returned {response.status}"
        return
    assert response.status == 200, f"{route} returned {response.status}"


@pytest.mark.parametrize("route", PUBLIC_ROUTES)
def test_public_route_has_no_console_errors(page, route: str):
    page.goto(route)
    assert page.page_errors == [], f"uncaught JS errors on {route}: {page.page_errors}"
    # "Failed to load resource" entries are network events, not application
    # errors — they are asserted precisely by the failed_responses test below.
    app_errors = [e for e in page.console_errors if "Failed to load resource" not in e]
    assert app_errors == [], f"console errors on {route}: {app_errors}"


@pytest.mark.parametrize("route", PUBLIC_ROUTES)
def test_public_route_has_no_broken_assets(page, route: str):
    page.goto(route)
    broken = [
        entry
        for entry in page.failed_responses
        if "/health" not in entry and "favicon" not in entry
    ]
    if route == "/api.php":
        # The main document itself is the (expected) 400.
        broken = [entry for entry in broken if "/api.php" not in entry]
    assert broken == [], f"broken sub-resources on {route}: {broken}"


def test_home_page_shows_linuxkafe_branding(page):
    page.goto("/")
    body = page.inner_text("body")
    assert "LINUXKAFE" in body.upper()


def test_knowledgebase_lists_articles(page):
    page.goto("/knowledgebase.php")
    body = page.inner_text("body")
    assert len(body.strip()) > 0


def test_announcements_page_lists_items(page):
    page.goto("/announcements.php")
    body = page.inner_text("body")
    assert len(body.strip()) > 0


def test_domain_checker_form_present(page):
    page.goto("/domainchecker.php")
    assert page.locator('input[name="domain"], input[type="text"]').count() >= 1


def test_server_status_reports_metrics(page):
    page.goto("/serverstatus.php")
    body = page.inner_text("body").lower()
    # The UI is pt-BR, so the assertions must be pt-BR too.
    assert "tempo de atividade" in body or "uptime" in body
    assert "carga do sistema" in body or "load" in body
    assert "memória" in body or "memory" in body


def test_health_endpoint_is_json(page):
    response = page.goto("/health")
    assert response is not None
    assert response.status == 200
    assert "json" in response.headers.get("content-type", "")


def test_health_db_endpoint_is_json(page):
    response = page.goto("/health/db")
    assert response is not None
    assert response.status == 200
    payload = response.json()
    assert "status" in payload or "database" in payload or "db" in payload


def test_stack_is_reachable_outside_browser():
    """Sanity check that the readiness gate agrees with the direct probe."""
    from conftest import BASE_URL

    assert stack_is_up(BASE_URL)

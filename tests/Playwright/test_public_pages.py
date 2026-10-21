"""
The public surface after the migration to Flutter (T025).

The client area is the Flutter application. The PHP that remains is the API,
the admin panel and the health endpoints — and those are what this module
covers, together with a smoke test of the application itself.
"""

from __future__ import annotations

import pytest

from conftest import PUBLIC_ROUTES, stack_is_up

APP_URL = "http://localhost:8083/"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.mark.parametrize("route", PUBLIC_ROUTES)
def test_public_route_answers(page, route: str):
    response = page.goto(route)

    assert response is not None, f"no response for {route}"
    assert response.status in (200, 302, 400, 401), f"{route} returned {response.status}"


def test_the_root_is_the_flutter_application(page):
    response = page.goto(APP_URL)

    assert response is not None
    assert response.status == 200
    assert "flutter" in page.content().lower()


def test_the_application_boots(page):
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(2000)

    assert page.locator("flutter-view").count() == 1
    assert page.page_errors == [], page.page_errors


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
    from conftest import BASE_URL, stack_is_up

    assert stack_is_up(BASE_URL)

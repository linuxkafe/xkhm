"""
The authenticated pages of the PHP backend.

These routes are only reachable with a session, so they are invisible to an
anonymous crawl — which is exactly how a missing import (a fatal error on
`/affiliates.php`) can hide. This module logs in and visits every one.
"""

from __future__ import annotations

import pytest

from conftest import CLIENT_PASS, CLIENT_USER

#: Routes that must render for an authenticated client.
CLIENT_ROUTES = [
    "/clientarea.php",
    "/clientarea.php?action=invoices",
    "/clientarea.php?action=orders",
    "/affiliates.php",
    "/domain.php",
    "/ssl.php",
    "/supporttickets.php",
    "/viewinvoice.php?id=1",
]


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _login(page) -> None:
    page.goto("/clientarea.php")
    page.fill('input[name="email"], input[name="username"]', CLIENT_USER)
    page.fill('input[name="password"]', CLIENT_PASS)
    page.click('button[type="submit"], input[type="submit"]')
    page.wait_for_url("**/clientarea.php", timeout=15_000)


@pytest.mark.parametrize("route", CLIENT_ROUTES)
def test_authenticated_route_renders(page, route: str):
    _login(page)
    response = page.goto(route)

    assert response is not None, f"no response for {route}"
    assert response.status == 200, f"{route} returned {response.status}"
    assert "Fatal error" not in page.content(), f"{route} raised a PHP error"
    assert "Slim Application Error" not in page.content(), f"{route} raised an error"


@pytest.mark.parametrize("route", CLIENT_ROUTES)
def test_authenticated_route_is_styled(page, route: str):
    _login(page)
    page.goto(route)
    html = page.content()

    assert 'class="site-header"' in html, f"{route} has no site header"
    assert 'href="/templates/default/css/whmcs.css"' in html, f"{route} is unstyled"


@pytest.mark.parametrize("route", CLIENT_ROUTES)
def test_authenticated_route_has_no_console_errors(page, route: str):
    _login(page)
    page.goto(route)

    assert page.page_errors == [], page.page_errors
    app_errors = [e for e in page.console_errors if "Failed to load resource" not in e]
    assert app_errors == [], app_errors

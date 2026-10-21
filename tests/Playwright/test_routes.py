"""
The route topology after the migration to Flutter (T025).

The client area is the Flutter application, served at `/`. The HTML pages
are retired: they answer with a redirect to the application, so no bookmark
breaks and no client-facing page serves HTML any more.

The PHP stays as the API server, the admin panel and the health endpoints.
"""

from __future__ import annotations

import pytest

from conftest import stack_is_up

#: The pages the application replaced. They must not serve HTML any more.
RETIRED_ROUTES = [
    "/clientarea.php",
    "/dologin.php",
    "/logout.php",
    "/pwreset.php",
    "/domainchecker.php",
    "/domain.php",
    "/viewinvoice.php",
    "/knowledgebase.php",
    "/supporttickets.php",
    "/ssl.php",
    "/serverstatus.php",
    "/announcements.php",
    "/affiliates.php",
]

#: The routes the PHP keeps: the API, the admin and the health checks.
SURVIVING_ROUTES = [
    "/api/v1/health",
    "/health",
    "/health/db",
    "/admin/login.php",
]


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def test_the_root_serves_the_flutter_application(page):
    """`/` is the application — not a PHP page."""
    response = page.goto("/")

    assert response is not None
    assert response.status == 200
    assert "text/html" in response.headers.get("content-type", "")

    html = page.content()
    assert "<base href=" in html
    # The Flutter bundle is what the browser runs.
    assert "flutter_bootstrap.js" in html or "flutter.js" in html


def test_the_application_boots_at_the_root(page):
    page.goto("/")
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(2000)

    assert page.locator("flutter-view").count() == 1
    assert page.page_errors == [], page.page_errors


@pytest.mark.parametrize("route", RETIRED_ROUTES)
def test_retired_routes_redirect_to_the_application(page, route: str):
    """A retired page must not serve HTML: it redirects to the application."""
    response = page.goto(route)

    assert response is not None
    # Playwright follows the redirect, so the final URL is the assertion.
    from conftest import BASE_URL

    assert page.url.rstrip("/") == BASE_URL.rstrip("/"), (
        f"{route} landed on {page.url} instead of the application"
    )

    # And the target really is the application.
    assert page.locator("flutter-view").count() == 1


@pytest.mark.parametrize("route", SURVIVING_ROUTES)
def test_surviving_routes_still_answer(page, route: str):
    response = page.goto(route)

    assert response is not None, f"{route} did not answer"
    assert response.status in (200, 302, 401), f"{route} returned {response.status}"


def test_the_legacy_whmcs_api_still_answers(page):
    """`/api.php` is used by external integrations; it is not a front-end."""
    response = page.goto("/api.php?action=getdomaininfo&domain=example.com")

    assert response is not None
    assert "json" in response.headers.get("content-type", "")


def test_the_api_v1_surface_is_intact(page):
    response = page.goto("/api/v1/health")

    assert response is not None
    assert response.status == 200
    payload = response.json()
    assert payload["ok"] is True
    assert payload["data"]["api"] == "v1"


@pytest.mark.parametrize(
    "endpoint",
    [
        "/api/v1/home",
        "/api/v1/client/summary",
        "/api/v1/client/invoices",
        "/api/v1/client/orders",
        "/api/v1/client/domains",
        "/api/v1/client/ssl",
        "/api/v1/client/affiliate",
        "/api/v1/client/tickets",
    ],
)
def test_the_client_endpoints_require_a_session(page, endpoint: str):
    """The client-scoped endpoints are all behind the session guard."""
    response = page.request.get(endpoint)

    assert response.status == 401, f"{endpoint} answered {response.status}"
    assert response.json()["error"]["code"] == "UNAUTHORIZED"


@pytest.mark.parametrize(
    "endpoint",
    [
        "/api/v1/knowledgebase",
        "/api/v1/knowledgebase/categories",
        "/api/v1/announcements",
        "/api/v1/serverstatus",
        "/api/v1/domains/pricing",
    ],
)
def test_the_public_endpoints_answer_without_a_session(page, endpoint: str):
    """The knowledge base, the news and the status are public by design."""
    response = page.request.get(endpoint)

    assert response.status == 200, f"{endpoint} answered {response.status}"
    assert response.json()["ok"] is True

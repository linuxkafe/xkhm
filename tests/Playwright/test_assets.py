"""
Asset-linkage invariant: no HTML page may ship unstyled.

This module is the automated guard against the "sem CSS" defect: every
server-rendered page must link the LINUXKAFE stylesheets and must not
reference any sub-resource that 404s.
"""

from __future__ import annotations

import pytest

from conftest import HTML_ROUTES, PROTECTED_ROUTES

REQUIRED_STYLESHEETS = [
    "/templates/default/css/tokens.css",
    "/templates/default/css/whmcs.css",
]


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_links_required_stylesheets(page, route: str):
    page.goto(route)
    html = page.content()
    for href in REQUIRED_STYLESHEETS:
        assert f'href="{href}"' in html, f"{route} does not link {href}"


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_has_site_chrome(page, route: str):
    """Every page carries the site header and the footer."""
    page.goto(route)
    html = page.content()
    assert 'class="site-header"' in html, f"{route} has no site header"
    assert "<footer" in html, f"{route} has no footer"


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_stylesheets_resolve(page, route: str):
    """Each linked stylesheet must actually be served."""
    page.goto(route)
    for href in REQUIRED_STYLESHEETS:
        response = page.request.get(href)
        assert response is not None
        assert response.status == 200, f"{href} returned {response.status}"
        assert "text/css" in response.headers.get("content-type", "")


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_declares_language_and_charset(page, route: str):
    page.goto(route)
    html = page.content()
    assert 'lang="pt-BR"' in html, f"{route} is not declared as pt-BR"
    assert 'charset="utf-8"' in html, f"{route} has no charset declaration"


@pytest.mark.parametrize("route", HTML_ROUTES)
def test_page_is_responsive(page, route: str):
    """A viewport meta tag is required for the mobile layout."""
    page.goto(route)
    html = page.content()
    assert "viewport" in html, f"{route} has no viewport meta tag"


def test_protected_pages_are_also_styled(page):
    """Even redirect targets must be styled — a 302 target is a real page."""
    for route in PROTECTED_ROUTES:
        page.goto(route)
        page.wait_for_load_state("domcontentloaded")
        html = page.content()
        if "<title>" not in html:
            continue
        assert 'class="site-header"' in html, f"{route} has no site header"

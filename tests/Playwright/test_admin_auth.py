"""Admin authentication: login, protected routes, logout."""

from __future__ import annotations

import pytest

from conftest import ADMIN_PASS, ADMIN_USER, admin_login


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def test_admin_login_page_renders_form(page):
    page.goto("/admin/login.php")
    assert page.locator('input[name="username"]').count() == 1
    assert page.locator('input[name="password"]').count() == 1


def test_admin_login_with_valid_credentials(page):
    admin_login(page)
    assert "/admin" in page.url
    body = page.inner_text("body")
    assert "ADMIN" in body.upper()


def test_admin_login_with_invalid_password_is_rejected(page):
    page.goto("/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', "definitely-not-the-password")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin/login.php**", timeout=15_000)
    assert "error" in page.url
    assert "Admin Panel" not in page.inner_text("body")


def test_protected_admin_route_redirects_anonymous(page):
    page.goto("/admin")
    page.wait_for_load_state("domcontentloaded")
    # page.goto follows the redirect, so the final URL is the assertion target.
    assert "/admin/login.php" in page.url, f"expected redirect to login, got {page.url}"


def test_admin_session_persists_across_navigation(page):
    admin_login(page)
    page.goto("/admin/login.php")
    # An authenticated session must not show the login form again.
    assert page.locator('input[name="password"]').count() == 0


def test_admin_logout_clears_session(page):
    admin_login(page)
    page.goto("/admin/logout.php")
    page.wait_for_url("**/admin/login.php**", timeout=15_000)
    # Session must be dead: /admin must redirect again.
    page.goto("/admin")
    page.wait_for_load_state("domcontentloaded")
    assert "/admin/login.php" in page.url, f"session survived logout: {page.url}"

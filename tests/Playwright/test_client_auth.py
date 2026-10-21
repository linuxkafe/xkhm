"""Client authentication against the real WHMCS-schema client table."""

from __future__ import annotations

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, client_login


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def test_clientarea_shows_login_form_when_anonymous(page):
    page.goto("/clientarea.php")
    assert page.locator('input[name="password"]').count() == 1


def test_client_login_with_valid_credentials(page):
    client_login(page)
    assert "/clientarea.php" in page.url
    body = page.inner_text("body")
    assert "LINUXKAFE" in body.upper() or "CLIENT" in body.upper()


def test_client_login_with_invalid_password_is_rejected(page):
    page.goto("/clientarea.php")
    page.fill('input[name="email"], input[name="username"]', CLIENT_USER)
    page.fill('input[name="password"]', "definitely-not-the-password")
    page.click('button[type="submit"], input[type="submit"]')
    page.wait_for_load_state("domcontentloaded")
    # Must not have reached an authenticated client area.
    body = page.inner_text("body").lower()
    assert "inválid" in body or "invalid" in body, f"no error shown, body={body[:200]}"


def test_client_session_persists_across_navigation(page):
    client_login(page)
    page.goto("/clientarea.php")
    assert page.locator('input[name="password"]').count() == 0


def test_client_logout_clears_session(page):
    client_login(page)
    page.goto("/logout.php")
    page.goto("/clientarea.php")
    assert page.locator('input[name="password"]').count() == 1

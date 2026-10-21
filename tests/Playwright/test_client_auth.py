"""
Client authentication, now through the API and the Flutter application.

The PHP login page was retired in T025; the session is created by
`POST /api/v1/auth/login` and used by both the API and the application —
they share the same PHP session cookie.
"""

from __future__ import annotations

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

APP_URL = "http://localhost:8083/"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def test_login_with_valid_credentials(page):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )

    assert response.status == 200
    payload = response.json()
    assert payload["ok"] is True
    assert payload["data"]["client"]["email"] == CLIENT_USER


def test_login_with_invalid_password_is_rejected(page):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": "definitely-not-the-password"},
    )

    assert response.status == 401
    assert response.json()["error"]["code"] == "UNAUTHORIZED"


def test_login_with_an_unknown_email_is_rejected(page):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": "nobody@example.com", "password": "whatever"},
    )

    assert response.status == 401


def test_login_requires_both_fields(page):
    response = page.request.post("/api/v1/auth/login", data={"email": CLIENT_USER})

    assert response.status == 400
    assert response.json()["error"]["code"] == "BAD_REQUEST"


def test_the_session_is_reusable(page):
    """The cookie returned by the login works on the other endpoints."""
    page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )

    response = page.request.get("/api/v1/auth/session")

    assert response.status == 200
    assert response.json()["data"]["client"]["email"] == CLIENT_USER


def test_logout_invalidates_the_session(page):
    page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )
    page.request.post("/api/v1/auth/logout")

    response = page.request.get("/api/v1/auth/session")

    assert response.status == 401


def test_the_forgot_password_answer_does_not_reveal_the_account(page):
    """The answer is the same whether or not the account exists."""
    known = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": CLIENT_USER}
    )
    unknown = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": "nobody@example.com"}
    )

    assert known.status == 200
    assert unknown.status == 200
    assert known.json()["data"]["message"] == unknown.json()["data"]["message"]


def test_the_application_shows_the_login_screen(page):
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)

    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)
    page.wait_for_timeout(1500)

    body = page.locator("flt-semantics-host").inner_text().lower()
    assert "iniciar sessão" in body
    assert "entrar" in body

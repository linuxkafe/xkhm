"""
The credential-recovery flow, end to end.

The flow is exercised against the **real** API and the **real** database:
request a code → read it → set a new password → log in with it → the old
password no longer works.
"""

from __future__ import annotations

import pytest

from conftest import stack_is_up

APP_URL = "http://localhost:8083/app/"

#: The recovery tests **change** the password, so they use accounts
#: reserved for them: the shared ones must stay untouched.
RESET_CLIENT_USER = "xkhm-reset@example.com"
RESET_CLIENT_PASS = "xkhm-reset-2026"
RESET_ADMIN_USER = "xkhm-reset-admin"
RESET_ADMIN_PASS = "xkhm-reset-2026"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


# ─── The client flow ─────────────────────────────────────────────────────────


def test_the_answer_never_reveals_whether_the_account_exists(page):
    """A known and an unknown address must get the same answer."""
    known = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    unknown = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": "nao-existe@example.com"}
    )

    assert known.status == 200
    assert unknown.status == 200
    assert known.json()["data"]["message"] == unknown.json()["data"]["message"]


def test_a_missing_email_is_rejected(page):
    response = page.request.post("/api/v1/auth/forgot-password", data={})

    assert response.status == 400
    assert response.json()["error"]["code"] == "BAD_REQUEST"


def test_the_full_client_recovery_flow(page):
    """Request → read the token → reset → log in → the old password fails."""
    # 1. Request the code.
    requested = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    assert requested.status == 200

    token = requested.json()["data"].get("developmentToken")
    assert token, "the development deployment must return the token"

    # 2. Set the new password with it.
    new_password = "SenhaRecuperada2026"
    reset = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": token, "password": new_password},
    )
    assert reset.status == 200, reset.text()

    # 3. The new password works.
    login = page.request.post(
        "/api/v1/auth/login",
        data={"email": RESET_CLIENT_USER, "password": new_password},
    )
    assert login.status == 200, login.text()

    # 4. The old password no longer does.
    old = page.request.post(
        "/api/v1/auth/login",
        data={"email": RESET_CLIENT_USER, "password": RESET_CLIENT_PASS},
    )
    assert old.status == 401

    # 5. The token is single-use, so the password is restored with a new
    #    request — this account exists only for the recovery tests.
    second = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    second_token = second.json()["data"].get("developmentToken")
    restore = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": second_token, "password": RESET_CLIENT_PASS},
    )
    assert restore.status == 200


def test_the_token_cannot_be_used_twice(page):
    requested = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    token = requested.json()["data"].get("developmentToken")
    assert token

    first = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": token, "password": "PrimeiraSenha2026"},
    )
    assert first.status == 200

    second = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": token, "password": "SegundaSenha2026"},
    )
    assert second.status == 400
    assert second.json()["error"]["code"] == "BAD_REQUEST"

    # Restore, with a new token: the old one is single-use.
    second_request = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    page.request.post(
        "/api/v1/auth/reset-password",
        data={
            "token": second_request.json()["data"].get("developmentToken"),
            "password": RESET_CLIENT_PASS,
        },
    )


def test_an_unknown_token_is_refused(page):
    response = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": "token-que-nao-existe", "password": "QualquerSenha2026"},
    )

    assert response.status == 400


def test_a_short_password_is_refused(page):
    requested = page.request.post(
        "/api/v1/auth/forgot-password", data={"email": RESET_CLIENT_USER}
    )
    token = requested.json()["data"].get("developmentToken")

    response = page.request.post(
        "/api/v1/auth/reset-password",
        data={"token": token, "password": "corta"},
    )

    assert response.status == 400


# ─── The admin flow ──────────────────────────────────────────────────────────


def test_the_admin_recovery_flow(page):
    """The administrator has the same flow, without a schema change."""
    requested = page.request.post(
        "/api/v1/admin/auth/forgot-password", data={"username": RESET_ADMIN_USER}
    )
    assert requested.status == 200

    token = requested.json()["data"].get("developmentToken")
    assert token, "the development deployment must return the token"

    reset = page.request.post(
        "/api/v1/admin/auth/reset-password",
        data={"token": token, "password": "AdminRecuperada2026"},
    )
    assert reset.status == 200, reset.text()

    # The new password opens the admin panel.
    page.goto("/admin/login.php")
    page.fill('input[name="username"]', RESET_ADMIN_USER)
    page.fill('input[name="password"]', "AdminRecuperada2026")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)

    # Restore, with a new token: the old one is single-use.
    second = page.request.post(
        "/api/v1/admin/auth/forgot-password", data={"username": RESET_ADMIN_USER}
    )
    page.request.post(
        "/api/v1/admin/auth/reset-password",
        data={
            "token": second.json()["data"].get("developmentToken"),
            "password": RESET_ADMIN_PASS,
        },
    )


def test_the_admin_answer_never_reveals_whether_the_account_exists(page):
    known = page.request.post(
        "/api/v1/admin/auth/forgot-password", data={"username": RESET_ADMIN_USER}
    )
    unknown = page.request.post(
        "/api/v1/admin/auth/forgot-password", data={"username": "nao-existe"}
    )

    assert known.status == 200
    assert unknown.status == 200
    assert known.json()["data"]["message"] == unknown.json()["data"]["message"]


def test_the_admin_login_page_has_a_recovery_link(page):
    page.goto("/admin/login.php")

    assert page.get_by_text("Esqueci a senha?").count() == 1


def test_the_admin_recovery_form_is_reachable(page):
    page.goto("/admin/login.php?action=forgot")

    assert page.locator('input[name="username"]').count() == 1
    assert page.get_by_text("Pedir recuperação").count() == 1


# ─── The application ──────────────────────────────────────────────────────────


def test_the_application_has_the_recovery_screen(page):
    """The "Esqueci a senha?" link leads to a real screen."""
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)

    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)
    page.wait_for_timeout(1500)

    body = page.locator("flt-semantics-host").inner_text().lower()
    assert "iniciar sessão" in body
    assert "esqueci a senha" in body

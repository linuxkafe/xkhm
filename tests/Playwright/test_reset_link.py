"""
The recovery **link** — the whole path, from the e-mail to the form.

The link is the primary action of the recovery e-mail, and it carries a
credential in the URL. The two things that matter are therefore:

- the link **works**: it takes the person to the form, with the token
  already filled in;
- the token does not **leak**: it travels in the fragment, which the
  browser never sends to the server, and it is taken out of the address
  bar.
"""

from __future__ import annotations

import os
import pathlib
import re
import subprocess
import sys
import urllib.request

import pytest

from conftest import stack_is_up
from playwright.sync_api import expect

APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")
BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
MAILER = ROOT / "tools" / "mail_pending_tokens.py"

RESET_CLIENT_USER = "xkhm-reset@example.com"
RESET_ADMIN_USER = "xkhm-reset-admin"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _enable_semantics(page) -> None:
    """Flutter only materialises the semantics DOM on request."""
    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)


def _semantics_text(page) -> str:
    host = page.locator("flt-semantics-host")
    if host.count() == 0:
        return ""
    return host.inner_text()


def _fill(page, label: str, value: str, attempts: int = 3) -> None:
    """
    Type a value into a Flutter text field.

    Flutter keeps the real value in a `TextEditingController`; the DOM
    input is only a mirror. The field has to be really focused — or the
    first keystroke is lost to the focus transition — and the value has
    to have settled — or the server receives a truncated one.
    """
    field = page.get_by_label(label)

    for attempt in range(attempts):
        field.click()
        page.wait_for_timeout(150)

        field.fill("")
        page.wait_for_timeout(80)
        page.keyboard.type(value, delay=25)

        try:
            expect(field).to_have_value(value, timeout=5_000)
            page.wait_for_timeout(250)
            return
        except AssertionError:
            if attempt == attempts - 1:
                raise


# ─── The client: the link opens the form ─────────────────────────────────────


def test_the_link_opens_the_recovery_screen(page):
    """A link with the token opens the screen, with the token filled in."""
    page.goto(f"{APP_URL}#reset=TOKEN-DE-LINK")
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)
    page.wait_for_timeout(1500)

    body = _semantics_text(page).lower()

    # The screen is the one that sets the password — not the request.
    assert "definir nova senha" in body

    # The token is already in the field. Flutter's semantics element
    # only carries the value once the field is focused — that is when
    # the browser would show it to the user too.
    field = page.locator('input[aria-label="Código recebido"]')
    assert field.count() == 1
    field.click()
    page.wait_for_timeout(500)
    assert field.input_value() == "TOKEN-DE-LINK"


def test_the_token_leaves_the_address_bar(page):
    """
    The token must not stay in the address bar.

    Otherwise it sits in the history, where the back button finds it,
    and a reload tries to use it a second time.
    """
    page.goto(f"{APP_URL}#reset=TOKEN-DE-LINK")
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(1500)

    assert "token-de-link" not in page.url.lower()
    assert "#reset" not in page.url


def test_the_query_string_also_works(page):
    """
    The query string is the fallback.

    Some links (and some browsers) do not carry a fragment.
    """
    page.goto(f"{APP_URL}?reset=TOKEN-DA-QUERY")
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)
    page.wait_for_timeout(1500)

    body = _semantics_text(page).lower()

    assert "definir nova senha" in body

    field = page.locator('input[aria-label="Código recebido"]')
    field.click()
    page.wait_for_timeout(500)
    assert field.input_value() == "TOKEN-DA-QUERY"

    # And it leaves the address bar too.
    assert "reset=" not in page.url.lower()


def test_the_token_never_reaches_the_server(page):
    """
    The fragment is never sent to the server.

    This is the property that keeps the token out of the access log —
    and it is worth asserting, because it is the whole reason the
    fragment is used instead of the query string.
    """
    requests: list[str] = []

    def record(request):
        requests.append(request.url)

    page.on("request", record)

    page.goto(f"{APP_URL}#reset=TOKEN-DE-LINK")
    page.wait_for_selector("flutter-view", timeout=30_000)
    page.wait_for_timeout(1500)

    assert requests, "the page must have loaded"

    for url in requests:
        assert "token-de-link" not in url.lower(), (
            f"the token reached the server: {url}"
        )


def test_the_link_defines_the_new_password(page):
    """
    The whole point of the link: it defines the new password.

    This is the path the e-mail asks the customer to take, from the
    first click to the moment the new password works.
    """
    # A real token, for the real account.
    response = page.request.post(
        "/api/v1/auth/forgot-password",
        data={"email": RESET_CLIENT_USER},
    )
    token = response.json()["data"].get("developmentToken")
    assert token

    # The link, as the e-mail carries it.
    page.goto(f"{APP_URL}#reset={token}")
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)
    page.wait_for_timeout(1500)

    # The screen opens on the new password — the code is already there.
    assert "definir nova senha" in _semantics_text(page).lower()

    _fill(page, "Nova senha", "SenhaPeloLink2026")
    page.get_by_role("button", name="Definir nova senha").click()
    page.wait_for_timeout(3000)

    body = _semantics_text(page).lower()
    assert "alterada com sucesso" in body, body

    # The new password works, and the old one does not.
    login = page.request.post(
        "/api/v1/auth/login",
        data={"email": RESET_CLIENT_USER, "password": "SenhaPeloLink2026"},
    )
    assert login.status == 200, login.text()

    old = page.request.post(
        "/api/v1/auth/login",
        data={"email": RESET_CLIENT_USER, "password": "xkhm-reset-2026"},
    )
    assert old.status == 401

    # Put the password back.
    again = page.request.post(
        "/api/v1/auth/forgot-password",
        data={"email": RESET_CLIENT_USER},
    )
    page.request.post(
        "/api/v1/auth/reset-password",
        data={
            "token": again.json()["data"].get("developmentToken"),
            "password": "xkhm-reset-2026",
        },
    )


# ─── The administrator: the page that was missing ───────────────────────────


def test_the_admin_link_opens_the_form(page):
    """The link takes the administrator to the form, with the token."""
    page.goto(f"{BASE_URL}/admin/login.php?action=reset#reset=TOKEN-DO-ADMIN")

    field = page.locator('input[name="token"]')
    assert field.count() == 1, "the form must have the token field"

    page.wait_for_timeout(500)

    assert field.input_value() == "TOKEN-DO-ADMIN", "the token must be filled in"


def test_the_admin_token_leaves_the_address_bar(page):
    """The token does not stay in the address bar."""
    page.goto(f"{BASE_URL}/admin/login.php?action=reset#reset=TOKEN-DO-ADMIN")
    page.wait_for_timeout(500)

    assert "token-do-admin" not in page.url.lower()
    assert "#reset" not in page.url


def test_the_admin_form_sets_the_password(page):
    """The whole path: the link, the form, and the new password."""
    # Ask for a recovery.
    page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )

    # The link, as the e-mail carries it.
    response = page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    token = response.json()["data"].get("developmentToken")
    assert token

    page.goto(f"{BASE_URL}/admin/login.php?action=reset#reset={token}")
    page.wait_for_timeout(500)

    page.fill('input[name="password"]', "AdminPeloLink2026")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin/login.php?reset=1", timeout=15_000)

    # The new password opens the admin panel.
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', RESET_ADMIN_USER)
    page.fill('input[name="password"]', "AdminPeloLink2026")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)

    # Put it back.
    again = page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    page.request.post(
        "/api/v1/admin/auth/reset-password",
        data={
            "token": again.json()["data"].get("developmentToken"),
            "password": "xkhm-reset-2026",
        },
    )


def test_the_admin_code_can_be_pasted(page):
    """
    Without JavaScript the code is pasted by hand.

    The field is visible and editable on purpose: the e-mail carries
    the code as well as the link.
    """
    page.goto(f"{BASE_URL}/admin/login.php?action=reset")

    field = page.locator('input[name="token"]')
    assert field.count() == 1
    assert field.is_visible(), "the code field must be visible"
    assert field.is_editable(), "the code field must be editable"

    page.fill('input[name="token"]', "CODIGO-COLADO")
    assert field.input_value() == "CODIGO-COLADO"


def test_the_admin_page_has_the_form(page):
    """The page that was missing now exists."""
    page.goto(f"{BASE_URL}/admin/login.php?action=reset")

    assert page.get_by_text("Definir nova senha").count() >= 1
    assert page.locator('input[name="password"]').count() == 1


# ─── The e-mail itself ──────────────────────────────────────────────────────


def test_the_email_carries_a_link():
    """The e-mail carries a link, and the code as the alternative."""
    import importlib.util

    spec = importlib.util.spec_from_file_location("mailer", MAILER)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)

    fields = {
        "purpose": "cliente",
        "recipient": "alguem@exemplo.pt",
        "account": "alguem@exemplo.pt",
        "token": "TOKEN-DO-EMAIL",
    }

    message = module.build_message(fields, "https://xk.linuxkafe.com")

    assert message["Subject"]

    text = message.get_body(preferencelist=("plain",)).get_content()
    html = message.get_body(preferencelist=("html",)).get_content()

    # The link is the primary action.
    assert "https://xk.linuxkafe.com/app/#reset=TOKEN-DO-EMAIL" in text
    assert "https://xk.linuxkafe.com/app/#reset=TOKEN-DO-EMAIL" in html

    # The code is the alternative.
    assert "TOKEN-DO-EMAIL" in text
    assert "TOKEN-DO-EMAIL" in html

    # And the token travels in the fragment, not in the query string.
    assert "?reset=" not in text
    assert "?reset=" not in html


def test_the_admin_email_carries_the_admin_link():
    """The administrator's link goes to the page that sets the password."""
    import importlib.util

    spec = importlib.util.spec_from_file_location("mailer", MAILER)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)

    fields = {
        "purpose": "admin",
        "recipient": "mail@linuxkafe.com",
        "account": "linuxkafe",
        "token": "TOKEN-ADMIN",
    }

    message = module.build_message(fields, "https://xk.linuxkafe.com")

    text = message.get_body(preferencelist=("plain",)).get_content()

    assert (
        "https://xk.linuxkafe.com/admin/login.php?action=reset#reset=TOKEN-ADMIN"
        in text
    )

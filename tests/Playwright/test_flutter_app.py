"""
End-to-end tests for the Flutter web build.

The build is served by the PHP application at `/app/`, i.e. the *same origin*
as the `/api/v1` backend. That is deliberate: the browser then owns the
session cookie natively and the application needs no CORS.

Flutter renders to a canvas, so the DOM does not contain the widgets. The
accessibility (semantics) tree does — Flutter materialises it as
`flt-semantics` elements once the hidden "Enable accessibility" placeholder
is activated. Every assertion below reads that tree.
"""

from __future__ import annotations

import os
import re

import pytest
from playwright.sync_api import expect

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

#: The Flutter web build is mounted into the PHP application.
APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")
API_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083")


def _semantics(page):
    """The element holding Flutter's accessibility tree."""
    return page.locator("flt-semantics-host")


def _semantics_text(page) -> str:
    """The text exposed by Flutter's accessibility tree."""
    host = _semantics(page)
    if host.count() == 0:
        return ""
    return host.inner_text()


def _enable_semantics(page) -> None:
    """Flutter only materialises the semantics DOM on request."""
    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        # The placeholder is positioned off-screen, so a real click fails.
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)


@pytest.fixture(scope="session")
def app_ready() -> bool:
    """Both the API and the Flutter build must be reachable."""
    import time
    import urllib.request

    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        try:
            with urllib.request.urlopen(APP_URL, timeout=2) as response:
                if response.status == 200 and stack_is_up(API_URL):
                    return True
        except OSError:
            pass
        time.sleep(1)
    return False


@pytest.fixture
def app_page(page):
    """A page on the Flutter application with the semantics tree enabled."""
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)
    page.wait_for_timeout(1500)
    return page


@pytest.fixture(autouse=True)
def _require_app(app_ready: bool):
    if not app_ready:
        pytest.skip(
            "Flutter build or API not reachable — run `make flutter-build` "
            "and `docker compose up -d`"
        )


def _fill(page, label: str, value: str) -> None:
    """Type a value into a Flutter text field.

    Flutter keeps the real value in a `TextEditingController`; the DOM input
    is only a mirror. Typing must therefore be *settled* before the form is
    submitted, otherwise the last keystrokes are still in flight and the
    server receives a truncated value.
    """
    field = page.get_by_label(label)
    field.click()
    page.keyboard.type(value, delay=25)
    expect(field).to_have_value(value)
    # Let the framework drain its input queue before the next interaction.
    page.wait_for_timeout(250)


def _login(page) -> None:
    """Perform the real login flow through the Flutter UI."""
    _fill(page, "Email", CLIENT_USER)
    _fill(page, "Senha", CLIENT_PASS)

    page.get_by_role("button", name="Entrar").click()
    page.wait_for_timeout(3000)


def _navigate(page, label: str, expected: str, attempts: int = 3) -> str:
    """Click a navigation-rail entry and wait for the destination to render.

    Flutter repaints the rail when the viewport changes, so a single click
    can land on a node that is about to be replaced. The click is therefore
    retried until the expected content appears.
    """
    for attempt in range(attempts):
        entry = page.get_by_role("button", name=re.compile(rf"^{label}", re.IGNORECASE))
        if entry.count() > 0:
            entry.first.click()
        try:
            expect(_semantics(page)).to_contain_text(expected, timeout=10_000)
            return _semantics_text(page)
        except AssertionError:
            if attempt == attempts - 1:
                raise
    return _semantics_text(page)


def test_flutter_app_boots_without_errors(app_page):
    assert app_page.locator("flutter-view").count() == 1
    assert app_page.page_errors == [], app_page.page_errors


def test_flutter_shows_the_pt_br_login_screen(app_page):
    body = _semantics_text(app_page).lower()
    assert "linuxkafé" in body
    assert "área do cliente" in body
    assert "iniciar sessão" in body
    assert "entrar" in body


def test_flutter_login_rejects_bad_credentials(app_page):
    _fill(app_page, "Email", CLIENT_USER)
    _fill(app_page, "Senha", "definitely-not-the-password")

    app_page.get_by_role("button", name="Entrar").click()
    app_page.wait_for_timeout(2500)

    body = _semantics_text(app_page)
    assert "inválid" in body.lower(), body


def test_flutter_login_reaches_the_dashboard(app_page):
    _login(app_page)
    body = _semantics_text(app_page)

    # Identity comes from the real `linuxkonect` client row.
    assert "linuxkafe" in body.lower()
    assert CLIENT_USER in body
    # The billing summary is read from `tblinvoices`.
    assert "resumo financeiro" in body.lower()
    assert "total em aberto" in body.lower()


def test_flutter_dashboard_shows_the_database_counts(app_page):
    _login(app_page)
    body = _semantics_text(app_page).lower()

    assert "serviços" in body
    assert "faturas" in body
    assert "tickets" in body
    assert "domínios" in body


def test_flutter_navigates_to_the_invoices(app_page):
    _login(app_page)
    app_page.set_viewport_size({"width": 1280, "height": 900})
    app_page.wait_for_timeout(800)

    body = _navigate(app_page, "Faturas", "Fatura #1")
    assert "fatura" in body.lower()


def test_flutter_navigates_to_the_knowledgebase(app_page):
    _login(app_page)
    app_page.set_viewport_size({"width": 1280, "height": 900})
    app_page.wait_for_timeout(800)

    body = _navigate(app_page, "Base de Conhecimento", "Categorias").lower()
    assert "artigos" in body


def test_flutter_navigates_to_the_server_status(app_page):
    _login(app_page)
    app_page.set_viewport_size({"width": 1280, "height": 900})
    app_page.wait_for_timeout(800)

    body = _navigate(app_page, "Status do Servidor", "Tempo de atividade").lower()
    assert "base de dados" in body


def test_flutter_navigates_to_the_domain_checker(app_page):
    _login(app_page)
    app_page.set_viewport_size({"width": 1280, "height": 900})
    app_page.wait_for_timeout(800)

    body = _navigate(app_page, "Procurar Domínio", "Verificar").lower()
    assert "domínio" in body

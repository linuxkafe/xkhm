"""
The administration panel, end to end.

The panel is the surface the operator uses every day, so the tests are
about **behaviour**, not about markup: the pages answer with the real
data, the filters narrow it, the search finds it, and the operations
that change something really change it.
"""

from __future__ import annotations

import os

import pytest

from conftest import ADMIN_PASS, ADMIN_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")

#: The areas of the panel.
AREAS = [
    "/admin",
    "/admin/clients.php",
    "/admin/invoices.php",
    "/admin/orders.php",
    "/admin/tickets.php",
    "/admin/domains.php",
    "/admin/affiliates.php",
    "/admin/search.php",
    "/admin/config.php",
]


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture
def admin(page):
    """A page with an administrator signed in."""
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', ADMIN_PASS)
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)
    return page


# ─── Every area answers ──────────────────────────────────────────────────────


def test_every_area_answers(admin):
    for area in AREAS:
        response = admin.goto(f"{BASE_URL}{area}")
        assert response.status == 200, f"{area} answered {response.status}"


def test_the_panel_needs_a_session(page):
    """Without a session, the panel sends the operator to the login."""
    for area in ["/admin", "/admin/clients.php", "/admin/config.php"]:
        page.goto(f"{BASE_URL}{area}")
        # The browser follows the redirect, so it is the **final** URL
        # that says the panel sent the operator away.
        assert page.url.endswith("/admin/login.php"), area


def test_the_panel_has_its_own_navigation(admin):
    admin.goto(f"{BASE_URL}/admin")

    # The navigation of the panel, and **not** the public one.
    for label in ["Clientes", "Faturas", "Pedidos", "Tickets", "Pesquisar"]:
        assert admin.locator(f".admin-nav a:has-text('{label}')").count() >= 1

    # The public header does not belong above the panel.
    assert admin.locator("header.site-header").count() == 0


def test_the_dashboard_shows_the_real_numbers(admin):
    admin.goto(f"{BASE_URL}/admin")

    # The CSS uppercases the labels, and `inner_text` returns what is
    # rendered — so the comparison is case-insensitive.
    body = admin.inner_text("body").lower()

    assert "clientes" in body
    assert "faturas pagas" in body
    assert "tickets abertos" in body
    assert "domínios" in body

    # The real figures of the production database.
    assert "10" in body


def test_the_client_list_shows_the_real_clients(admin):
    admin.goto(f"{BASE_URL}/admin/clients.php")

    body = admin.inner_text("body")

    assert "xkhm-test@example.com" in body
    assert admin.locator("table.table tbody tr").count() > 0


def test_the_client_search_narrows_the_list(admin):
    admin.goto(f"{BASE_URL}/admin/clients.php")

    before = admin.locator("table.table tbody tr").count()

    admin.fill('input[name="q"]', "xkhm-test@example.com")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")
    after = admin.locator("table.table tbody tr").count()

    assert after <= before
    assert "xkhm-test@example.com" in body


def _total_registos(page) -> int:
    """
    The number of records the list holds.

    The pager only appears when there is more than one page: a list that
    fits in one page has no pager, and the total is the rows on it.
    """
    pager = page.locator(".admin-pager-info")

    if pager.count() == 0:
        return page.locator("table.table tbody tr").count()

    # "Página 1 de 4 · 90 registos"
    text = pager.inner_text()

    return int(text.split("·")[-1].strip().split()[0])


def test_the_invoice_filter_works(admin):
    """The filter really narrows the list."""
    admin.goto(f"{BASE_URL}/admin/invoices.php")
    everything = _total_registos(admin)

    admin.goto(f"{BASE_URL}/admin/invoices.php?status=Paid")
    paid = _total_registos(admin)

    admin.goto(f"{BASE_URL}/admin/invoices.php?status=Unpaid")
    unpaid = _total_registos(admin)

    assert paid > 0
    assert unpaid > 0
    assert paid + unpaid < everything, "the filter must narrow the list"

    # And every row of the paid list is paid.
    admin.goto(f"{BASE_URL}/admin/invoices.php?status=Paid")
    badges = admin.locator("table.table tbody .badge").all_inner_texts()
    assert badges
    assert set(badges) == {"Paid"}


def test_the_ticket_filter_works(admin):
    admin.goto(f"{BASE_URL}/admin/tickets.php?status=Open")

    assert admin.locator("table.table tbody tr").count() > 0

    badges = admin.locator("table.table tbody .badge").all_inner_texts()
    assert badges
    assert set(badges) == {"Open"}


def test_the_pagination_works(admin):
    """There are more invoices than a page holds."""
    admin.goto(f"{BASE_URL}/admin/invoices.php")

    assert admin.locator(".admin-pager").count() == 1

    first_page = admin.inner_text("table.table tbody")
    admin.click(".admin-pager a:has-text('Seguinte')")
    admin.wait_for_load_state("networkidle")

    second_page = admin.inner_text("table.table tbody")

    assert first_page != second_page, "the second page must be different"


def test_the_client_detail_shows_the_clients_rows(admin):
    admin.goto(f"{BASE_URL}/admin/clients.php")

    # Follow the first client.
    admin.click("table.table tbody tr:first-child td:nth-child(2) a")
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")

    assert "Faturas" in body
    assert "Pedidos" in body
    assert "Domínios" in body
    assert "Tickets" in body
    assert "Transações" in body


def test_the_invoice_detail_shows_the_items(admin):
    admin.goto(f"{BASE_URL}/admin/invoices.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body").lower()

    assert "itens" in body
    assert "total" in body


def test_the_global_search_finds(admin):
    admin.goto(f"{BASE_URL}/admin/search.php")

    admin.fill('input[name="q"]', "xkhm-test@example.com")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")

    assert "xkhm-test@example.com" in body


def test_the_configuration_is_visible(admin):
    admin.goto(f"{BASE_URL}/admin/config.php")

    body = admin.inner_text("body")

    # A chave da definicao, e o **nome real** por cima dela.
    assert "CompanyName" in body
    assert "Nome da Empresa" in body

    # O valor editável está no campo — `inner_text` não o traz.
    assert admin.locator('input[name="CompanyName"]').count() == 1
    assert admin.locator('input[name="CompanyName"]').input_value() == "LINUXKAFE"


# ─── The malformed requests ──────────────────────────────────────────────────


def test_a_client_that_does_not_exist_is_a_404(admin):
    response = admin.goto(f"{BASE_URL}/admin/clients.php?id=99999999")

    assert response.status == 404


def test_a_malformed_id_is_a_400(admin):
    """A request with a mistake in it says so, and does not show the list."""
    response = admin.goto(f"{BASE_URL}/admin/clients.php?id=abc")

    assert response.status == 400


def test_a_negative_id_is_a_400(admin):
    response = admin.goto(f"{BASE_URL}/admin/invoices.php?id=-5")

    assert response.status == 400


# ─── The write ───────────────────────────────────────────────────────────────


def test_the_administrator_can_reply_to_a_ticket(admin):
    """
    The one write of the panel: replying to a ticket.

    It has to **really** happen: the reply is in the database, and the
    ticket's detail shows it.
    """
    admin.goto(f"{BASE_URL}/admin/tickets.php")

    admin.click("table.table tbody tr:first-child td:nth-child(2) a")
    admin.wait_for_load_state("networkidle")

    message = "Resposta de teste do painel — verificação automática."

    admin.fill('textarea[name="message"]', message)
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")

    assert message in body, "the reply must be in the ticket's detail"
    assert "Administração" in body, "the reply must be attributed"


def test_a_ticket_that_does_not_exist_is_a_404(admin):
    response = admin.goto(f"{BASE_URL}/admin/tickets.php?id=99999999")

    assert response.status == 404

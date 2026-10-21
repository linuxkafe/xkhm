"""
The client-area data, now served by the API and rendered by the application.

The PHP pages were retired in T025, so the assertions are about the *data*:
each endpoint must return the rows of the `linuxkonect` table it names, and
the ownership check must hold.
"""

from __future__ import annotations

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

#: A second development client, used for the IDOR check. It owns none of
#: the first client's data.
OTHER_CLIENT_USER = "xkhm-test2@example.com"
OTHER_CLIENT_PASS = "xkhm-test-2026"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _login(page, email: str = CLIENT_USER, password: str = CLIENT_PASS):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": email, "password": password},
    )
    assert response.status == 200, response.text()
    return response.json()


def test_the_summary_reports_the_client_and_the_counters(page):
    _login(page)

    response = page.request.get("/api/v1/client/summary")

    assert response.status == 200
    data = response.json()["data"]

    assert data["client"]["email"] == CLIENT_USER
    assert "creditBalance" in data["billing"]
    assert "unpaidTotal" in data["billing"]
    for counter in ("invoices", "unpaidInvoices", "tickets", "openTickets", "domains"):
        assert counter in data["counts"], counter


def test_the_home_screen_data_is_present(page):
    _login(page)

    response = page.request.get("/api/v1/home")

    assert response.status == 200
    data = response.json()["data"]

    assert data["client"]["email"] == CLIENT_USER
    assert "billing" in data
    assert isinstance(data["announcements"], list)
    assert isinstance(data["articles"], list)


def test_the_invoice_list_is_not_empty(page):
    """The seeded invoice belongs to the client — it must be visible."""
    _login(page)

    response = page.request.get("/api/v1/client/invoices")

    assert response.status == 200
    invoices = response.json()["data"]["invoices"]
    assert len(invoices) > 0

    invoice = invoices[0]
    for field in ("id", "number", "status", "dateCreated", "dueDate", "total"):
        assert field in invoice, field


def test_the_invoice_detail_carries_its_items(page):
    _login(page)

    invoices = page.request.get("/api/v1/client/invoices").json()["data"]["invoices"]
    invoice_id = invoices[0]["id"]

    response = page.request.get(f"/api/v1/client/invoices/{invoice_id}")

    assert response.status == 200
    data = response.json()["data"]
    assert data["invoice"]["id"] == invoice_id
    assert len(data["items"]) > 0


def test_the_order_list_is_not_empty(page):
    _login(page)

    response = page.request.get("/api/v1/client/orders")

    assert response.status == 200
    orders = response.json()["data"]["orders"]
    assert len(orders) > 0
    assert "total" in orders[0]
    assert "status" in orders[0]


def test_the_domain_list_is_not_empty(page):
    _login(page)

    response = page.request.get("/api/v1/client/domains")

    assert response.status == 200
    domains = response.json()["data"]["domains"]
    assert len(domains) > 0
    assert "domain" in domains[0]
    assert "expiryDate" in domains[0]


def test_the_ssl_certificates_follow_the_domains(page):
    _login(page)

    domains = page.request.get("/api/v1/client/domains").json()["data"]["domains"]
    certificates = page.request.get("/api/v1/client/ssl").json()["data"]["certificates"]

    assert len(certificates) == len(domains)
    assert certificates[0]["domain"] == domains[0]["domain"]
    assert "daysRemaining" in certificates[0]


def test_the_affiliate_programme_is_readable(page):
    """The affiliate feature answers even when the client has no programme.

    The WHMCS 5.3.9 affiliate tables are empty in production, so "no
    programme" is the real answer, not an error.
    """
    _login(page)

    response = page.request.get("/api/v1/client/affiliate")

    assert response.status == 200
    data = response.json()["data"]
    assert "affiliate" in data
    assert "referrals" in data

    if data["affiliate"] is None:
        # No programme: the programme's terms are still advertised.
        assert data["program"]["commissionRate"] > 0
    else:
        assert "refCode" in data["affiliate"]


def test_the_ticket_list_is_not_empty(page):
    _login(page)

    response = page.request.get("/api/v1/client/tickets")

    assert response.status == 200
    tickets = response.json()["data"]["tickets"]
    assert len(tickets) > 0
    assert "subject" in tickets[0]


def test_a_ticket_can_be_created_and_is_then_listed(page):
    _login(page)

    before = page.request.get("/api/v1/client/tickets").json()["data"]["tickets"]

    created = page.request.post(
        "/api/v1/client/tickets",
        data={
            "subject": "Ticket de verificação E2E",
            "message": "Criado pela suíte de testes.",
        },
    )
    assert created.status == 201, created.text()

    after = page.request.get("/api/v1/client/tickets").json()["data"]["tickets"]
    assert len(after) == len(before) + 1


def test_the_knowledgebase_is_public_and_not_empty(page):
    response = page.request.get("/api/v1/knowledgebase")

    assert response.status == 200
    assert len(response.json()["data"]["articles"]) > 0


def test_the_knowledgebase_categories_report_their_counts(page):
    response = page.request.get("/api/v1/knowledgebase/categories")

    assert response.status == 200
    categories = response.json()["data"]["categories"]
    assert len(categories) > 0
    for category in categories:
        assert isinstance(category["articleCount"], int), category
        assert category["articleCount"] >= 0


def test_the_announcements_are_public(page):
    response = page.request.get("/api/v1/announcements")

    assert response.status == 200
    assert len(response.json()["data"]["announcements"]) > 0


def test_the_server_status_is_public(page):
    response = page.request.get("/api/v1/serverstatus")

    assert response.status == 200
    status = response.json()["data"]["status"]
    assert "uptime" in status
    assert "database" in status


def test_a_client_only_sees_its_own_data(page):
    """Ownership: a client must not read another client's rows."""
    _login(page, OTHER_CLIENT_USER, OTHER_CLIENT_PASS)

    invoices = page.request.get("/api/v1/client/invoices").json()["data"]["invoices"]
    tickets = page.request.get("/api/v1/client/tickets").json()["data"]["tickets"]

    assert invoices == []
    assert tickets == []

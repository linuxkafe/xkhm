"""
Security regression tests for the API surface.

These cover the defects found in the T021/T022 review:

  * the CORS middleware must not reflect an unknown origin
  * the client-scoped endpoints must require a session
  * a client must not read another client's invoice or ticket (IDOR)
  * an API failure must answer with the JSON envelope, never a stack trace
"""

from __future__ import annotations

import json

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

#: Every client-scoped endpoint. All of them must answer 401 anonymously.
CLIENT_SCOPED_ENDPOINTS = [
    "/api/v1/auth/session",
    "/api/v1/client/summary",
    "/api/v1/client/invoices",
    "/api/v1/client/invoices/1",
    "/api/v1/client/domains",
    "/api/v1/client/tickets",
    "/api/v1/client/tickets/1",
]

#: A second development client, used for the IDOR check. It owns none of
#: the first client's data.
OTHER_CLIENT_USER = "xkhm-test2@example.com"
OTHER_CLIENT_PASS = "xkhm-test-2026"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _login(page, email: str, password: str):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": email, "password": password},
    )
    assert response.status == 200, response.text()
    return response.json()


@pytest.mark.parametrize("endpoint", CLIENT_SCOPED_ENDPOINTS)
def test_client_endpoint_requires_a_session(page, endpoint: str):
    response = page.request.get(endpoint)

    assert response.status == 401, f"{endpoint} answered {response.status}"
    payload = response.json()
    assert payload["ok"] is False
    assert payload["error"]["code"] == "UNAUTHORIZED"


def test_client_endpoint_rejects_anonymous_post(page):
    response = page.request.post(
        "/api/v1/client/tickets",
        data={"subject": "x", "message": "y"},
    )

    assert response.status == 401
    assert response.json()["ok"] is False


def test_login_with_bad_credentials_is_rejected(page):
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": "definitely-not-the-password"},
    )

    assert response.status == 401
    assert response.json()["error"]["code"] == "UNAUTHORIZED"


def test_login_requires_both_fields(page):
    response = page.request.post("/api/v1/auth/login", data={"email": CLIENT_USER})

    assert response.status == 400
    assert response.json()["error"]["code"] == "BAD_REQUEST"


# ─── IDOR ─────────────────────────────────────────────────────────────────────


def test_a_client_cannot_read_another_clients_invoice(page):
    _login(page, OTHER_CLIENT_USER, OTHER_CLIENT_PASS)

    response = page.request.get("/api/v1/client/invoices/1")

    assert response.status == 404, f"answered {response.status}"
    assert response.json()["ok"] is False


def test_a_client_cannot_read_another_clients_ticket(page):
    _login(page, OTHER_CLIENT_USER, OTHER_CLIENT_PASS)

    response = page.request.get("/api/v1/client/tickets/1")

    assert response.status == 404
    assert response.json()["ok"] is False


def test_a_client_only_sees_its_own_data(page):
    _login(page, OTHER_CLIENT_USER, OTHER_CLIENT_PASS)

    invoices = page.request.get("/api/v1/client/invoices").json()
    tickets = page.request.get("/api/v1/client/tickets").json()

    assert invoices["data"]["invoices"] == []
    assert tickets["data"]["tickets"] == []


def test_legacy_api_endpoint_requires_a_session(page):
    """`/api.php` used to serve any invoice or ticket to an anonymous caller."""
    for action in ("getticketinfo&tid=1", "getinvoiceinfo&invid=1", "getclientinfo&clientid=1"):
        response = page.request.get(f"/api.php?action={action}")

        assert response.status == 401, f"{action} answered {response.status}"
        assert response.json()["result"] == "error"


# ─── CORS ─────────────────────────────────────────────────────────────────────


def test_cors_does_not_reflect_an_unknown_origin(page):
    response = page.request.get("/api/v1/health", headers={"Origin": "https://evil.example"})

    assert response.headers.get("access-control-allow-origin") is None
    assert response.headers.get("access-control-allow-credentials") is None


def test_cors_allows_a_configured_origin(page):
    response = page.request.get(
        "/api/v1/health", headers={"Origin": "http://localhost:8090"}
    )

    assert response.headers.get("access-control-allow-origin") == "http://localhost:8090"
    assert response.headers.get("access-control-allow-credentials") == "true"


def test_cors_preflight_is_answered(page):
    response = page.request.fetch(
        "/api/v1/auth/login",
        method="OPTIONS",
        headers={"Origin": "http://localhost:8090"},
    )

    assert response.status == 204
    assert response.headers.get("access-control-allow-origin") == "http://localhost:8090"


# ─── Error contract ───────────────────────────────────────────────────────────


def test_unknown_api_route_answers_json(page):
    response = page.request.get("/api/v1/definitely-not-a-route")

    assert response.status == 404
    assert "json" in response.headers.get("content-type", "")
    payload = response.json()
    assert payload["ok"] is False
    assert payload["error"]["code"] == "NOT_FOUND"


def test_api_error_never_leaks_a_stack_trace(page):
    response = page.request.get("/api/v1/knowledgebase/999999")

    assert response.status == 404
    body = response.text()
    assert "Stack trace" not in body
    assert "#0 " not in body
    assert "/var/www/html" not in body


def test_html_error_page_does_not_leak_a_stack_trace(page):
    response = page.request.get("/definitely-not-a-page")

    assert response.status == 404
    body = response.text()
    assert "Stack trace" not in body
    assert "/var/www/html" not in body


# ─── Ticket creation (the write path) ────────────────────────────────────────


def test_client_can_create_a_ticket(page):
    _login(page, CLIENT_USER, CLIENT_PASS)

    response = page.request.post(
        "/api/v1/client/tickets",
        data={"subject": "Ticket de teste E2E", "message": "Mensagem de teste."},
    )

    assert response.status == 201, response.text()
    payload = response.json()
    assert payload["ok"] is True
    assert payload["data"]["id"] > 0


def test_ticket_creation_validates_the_subject(page):
    _login(page, CLIENT_USER, CLIENT_PASS)

    response = page.request.post(
        "/api/v1/client/tickets",
        data={"subject": "", "message": "Mensagem."},
    )

    assert response.status == 400
    assert response.json()["error"]["code"] == "BAD_REQUEST"


def test_client_can_create_a_ticket_with_an_invalid_priority(page):
    """`tbltickets.priority` is an ENUM — an unknown value must not 500."""
    _login(page, CLIENT_USER, CLIENT_PASS)

    response = page.request.post(
        "/api/v1/client/tickets",
        data={
            "subject": "Prioridade inválida",
            "message": "Mensagem.",
            "priority": "not-a-valid-priority",
        },
    )

    assert response.status == 201, response.text()

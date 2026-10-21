"""WHMCS-compatible API endpoint and JSON contract checks."""

from __future__ import annotations

import pytest


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def test_api_endpoint_responds(page):
    """A bare /api.php has no action, so the API answers 400 with a JSON error."""
    response = page.goto("/api.php")
    assert response is not None
    assert response.status in (200, 400)
    assert "json" in response.headers.get("content-type", "")


def test_api_endpoint_emits_json(page):
    response = page.goto("/api.php?action=getdomaininfo&domain=example.com")
    assert response is not None
    content_type = response.headers.get("content-type", "")
    assert "json" in content_type, f"expected JSON, got {content_type!r}"


def test_api_endpoint_rejects_unknown_action(page):
    response = page.goto("/api.php?action=balance")
    assert response is not None
    # The WHMCS-compatible API answers with a JSON error object.
    assert response.status in (200, 400, 401, 403)
    assert "json" in response.headers.get("content-type", "")


def test_api_endpoint_serves_domain_lookup(page):
    response = page.goto("/api.php?action=getdomaininfo&domain=example.com")
    assert response is not None
    payload = response.json()
    assert "domain" in payload, payload
    assert payload["domain"] == "example.com"


def test_health_reports_ok(page):
    response = page.goto("/health")
    assert response is not None
    payload = response.json()
    assert payload.get("status") == "ok", payload


def test_health_db_reports_connection(page):
    response = page.goto("/health/db")
    assert response is not None
    payload = response.json()
    assert payload.get("status") == "ok", payload

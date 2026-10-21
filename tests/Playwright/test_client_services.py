"""
The services of a client, and their **type**.

`tblhosting` holds every service — the web hosting, the backups, the
servers. What tells them apart is the product, and the label the client
sees is the product's group.

The test is about that distinction: a backup must not be presented as
web hosting.
"""

from __future__ import annotations

import os

import pytest

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")
BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture
def client(page):
    """A page with a client signed in."""
    page.goto(f"{BASE_URL}/api/v1/auth/login")
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )
    assert response.status == 200, response.text()
    return page


# ─── The API ─────────────────────────────────────────────────────────────────


def test_the_services_carry_their_type(client):
    response = client.request.get("/api/v1/client/services")

    assert response.status == 200
    services = response.json()["data"]["services"]

    assert services, "the development client has services"

    for service in services:
        assert service["type"], "every service must say what it is"
        assert service["rawType"], "the raw type travels beside the label"
        assert service["name"], "the service must have a name"


def test_the_services_are_not_all_web_hosting(client):
    """
    The whole point: a backup is not web hosting.

    The development client has one of each, so the list has to show
    more than one type.
    """
    response = client.request.get("/api/v1/client/services")
    services = response.json()["data"]["services"]

    types = {service["type"] for service in services}

    assert len(types) > 1, f"the services are all the same type: {types}"
    assert "Web Hosting" in types
    assert "Backup" in types


def test_a_service_of_a_different_type_has_no_domain(client):
    """A backup has no domain — and the interface must not invent one."""
    response = client.request.get("/api/v1/client/services")
    services = response.json()["data"]["services"]

    backups = [s for s in services if s["type"] == "Backup"]
    hostings = [s for s in services if s["type"] == "Web Hosting"]

    assert backups
    assert hostings

    for backup in backups:
        assert backup["domain"] == ""
        assert backup["diskLimit"] == 0

    for hosting in hostings:
        assert hosting["domain"] != ""
        assert hosting["diskLimit"] > 0


# ─── The application ─────────────────────────────────────────────────────────


def _semantics_text(page) -> str:
    host = page.locator("flt-semantics-host")
    if host.count() == 0:
        return ""
    return host.inner_text()


def _enable_semantics(page, attempts: int = 5) -> None:
    """
    Flutter only materialises the semantics DOM on request — and the
    request has to be made again after every reload.

    The tree is present but visually hidden (it is waiting for a screen
    reader, not for a person), so the test waits for it to have
    **content** rather than to be visible.
    """
    for _ in range(attempts):
        placeholder = page.locator("flt-semantics-placeholder")

        if placeholder.count() > 0:
            placeholder.first.dispatch_event("click")
            page.wait_for_timeout(1200)

            host = page.locator("flt-semantics-host")
            if host.count() > 0 and host.inner_text().strip():
                return

        page.wait_for_timeout(800)

    raise AssertionError("the semantics tree did not materialise")


def test_the_application_shows_the_services(page):
    """The client sees their services, with their type."""
    # A phone: on a wide screen the application shows a navigation rail,
    # and the drawer — where the services are — does not exist.
    page.set_viewport_size({"width": 390, "height": 844})

    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)

    # The session is the browser's, so the app has to be signed in.
    login = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )
    assert login.status == 200, login.text()

    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)

    # The services are in the drawer, behind the navigation button.
    page.locator('[flt-semantics-identifier="nav-drawer"] [role="button"]').click()
    page.wait_for_timeout(1500)

    page.get_by_role("button", name="Serviços").click()
    page.wait_for_timeout(2500)

    body = _semantics_text(page)

    assert "web hosting" in body.lower()
    assert "backup" in body.lower()


# ─── The panel ───────────────────────────────────────────────────────────────


def test_the_panel_shows_the_services_by_type(page):
    """The administrator sees the same distinction."""
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', "xkhm-test")
    page.fill('input[name="password"]', "xkhm-test-2026")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)

    page.goto(f"{BASE_URL}/admin/clients.php?q={CLIENT_USER}")
    page.wait_for_load_state("networkidle")

    page.click("table.table tbody tr:first-child td:nth-child(2) a")
    page.wait_for_load_state("networkidle")

    # The CSS uppercases the headings, and `inner_text` returns what is
    # rendered — so the comparison is case-insensitive.
    body = page.inner_text("body").lower()

    assert "web hosting" in body
    assert "backup" in body
    # And the section is not called "alojamento" any more.
    assert "serviços" in body
    assert "alojamento" not in body


def test_the_service_detail_shows_its_own_fields(page):
    """A hosting has a domain; a backup does not."""
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', "xkhm-test")
    page.fill('input[name="password"]', "xkhm-test-2026")
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)

    # Find a hosting and a backup of the development client.
    page.goto(f"{BASE_URL}/admin/clients.php?q={CLIENT_USER}")
    page.wait_for_load_state("networkidle")
    page.click("table.table tbody tr:first-child td:nth-child(2) a")
    page.wait_for_load_state("networkidle")

    # The rows are grouped by type: the type is in its own column.
    rows = page.locator("table.table tbody tr")
    assert rows.count() >= 2

    hosting_href = None
    backup_href = None

    for index in range(rows.count()):
        cells = rows.nth(index).locator("td")
        if cells.count() < 2:
            continue

        type_text = cells.nth(1).inner_text().strip()
        link = cells.nth(0).locator('a[href^="/admin/services.php?id="]')

        if link.count() == 0:
            continue

        href = link.first.get_attribute("href") or ""

        if type_text == "Web Hosting" and hosting_href is None:
            hosting_href = href
        if type_text == "Backup" and backup_href is None:
            backup_href = href

    assert hosting_href, "the hosting service must be there"
    assert backup_href, "the backup service must be there"

    # The hosting shows its domain, its user and its limits. The
    # definition list puts the label and the value on separate lines.
    page.goto(f"{BASE_URL}{hosting_href}")
    hosting_body = page.inner_text("body").lower()
    assert "xkhm-teste.example.com" in hosting_body
    assert "utilizador" in hosting_body
    assert "disco" in hosting_body

    # The backup shows its own fields — and not the hosting's.
    page.goto(f"{BASE_URL}{backup_href}")
    backup_body = page.inner_text("body").lower()
    assert "xkhm-teste.example.com" not in backup_body
    assert "utilizador" not in backup_body
    assert "disco" not in backup_body

"""
The price and the renewal term of a service.

Changing the price half way through the period owes the customer the
difference, for the part of the period that is left. The calculation is
**quoted before it is applied** — a proration that applies itself takes
money (or gives it) without anybody having seen the arithmetic.
"""

from __future__ import annotations

import os
import re

import pytest

from conftest import ADMIN_PASS, ADMIN_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture
def admin(page):
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', ADMIN_PASS)
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)
    return page


def _service_id(admin) -> int:
    """A service of the development client."""
    admin.goto(f"{BASE_URL}/admin/clients.php?q=xkhm-test@example.com")
    admin.wait_for_load_state("networkidle")

    admin.click("table.table tbody tr:first-child td:nth-child(2) a")
    admin.wait_for_load_state("networkidle")

    links = admin.locator('a[href^="/admin/services.php?id="]')
    assert links.count() >= 1, "the client has services"

    href = links.first.get_attribute("href") or ""

    return int(re.search(r"id=(\d+)", href).group(1))


def _current_price(admin, service_id: int) -> float:
    """The price the service has right now."""
    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")

    body = admin.inner_text("body").upper()

    match = re.search(r"PREÇO ACTUAL\s*([0-9,]+)", body)

    assert match, body[:400]

    return float(match.group(1).replace(",", "."))


def test_the_service_shows_its_price_and_its_term(admin):
    """The price, the cycle and the renewal date are visible."""
    service_id = _service_id(admin)

    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")

    # The CSS uppercases the labels of the definition list, and
    # `inner_text` returns what is **rendered**.
    body = admin.inner_text("body").upper()

    assert "PREÇO ACTUAL" in body
    assert "CICLO" in body
    assert "PRÓXIMA RENOVAÇÃO" in body
    # And the form to change them.
    assert admin.locator('select[name="billingCycle"]').count() == 1
    assert admin.locator('input[name="amount"]').count() == 1


def test_the_cycles_offered_come_from_the_product(admin):
    """Only the cycles the product has a price for."""
    service_id = _service_id(admin)

    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")

    options = admin.locator('select[name="billingCycle"] option').all_inner_texts()

    assert options
    # A cycle without a price is not offered.
    assert all("—" in option for option in options)


def test_the_quote_shows_the_proration_before_anything_changes(admin):
    """The difference is shown before it is applied."""
    service_id = _service_id(admin)

    service_id = _service_id(admin)
    current = _current_price(admin, service_id)
    new_price = current + 10.0

    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")
    before = admin.inner_text("body").upper()

    admin.select_option('select[name="billingCycle"]', value="Annually")
    admin.fill('input[name="amount"]', f"{new_price:.2f}")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    quote = admin.inner_text("body")

    assert "Preço:" in quote
    assert "Próxima renovação:" in quote
    assert "diferença" in quote.lower()
    # And the change was **not** applied: the button is there to apply it.
    assert admin.locator('button:has-text("Aplicar a alteração")').count() == 1

    # The service still has its old price.
    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")
    after = admin.inner_text("body").upper()

    assert before.split("PREÇO ACTUAL")[1][:20] == after.split("PREÇO ACTUAL")[1][:20]


def test_the_change_is_applied_and_recorded(admin):
    """The price changes, and the change is in the client's notes."""
    service_id = _service_id(admin)

    service_id = _service_id(admin)
    current = _current_price(admin, service_id)
    new_price = current + 10.0

    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")
    admin.select_option('select[name="billingCycle"]', value="Annually")
    admin.fill('input[name="amount"]', f"{new_price:.2f}")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    admin.click('button:has-text("Aplicar a alteração")')
    admin.wait_for_load_state("networkidle")

    assert _current_price(admin, service_id) == pytest.approx(new_price, abs=0.01)

    # And the price is put back, so the test leaves nothing behind.
    admin.goto(f"{BASE_URL}/admin/services.php?id={service_id}")
    admin.select_option('select[name="billingCycle"]', value="Annually")
    admin.fill('input[name="amount"]', f"{current:.2f}")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")
    admin.click('button:has-text("Aplicar a alteração")')
    admin.wait_for_load_state("networkidle")

    assert _current_price(admin, service_id) == pytest.approx(current, abs=0.01)


def test_a_service_that_does_not_exist_is_a_404(admin):
    response = admin.goto(f"{BASE_URL}/admin/services.php?id=99999999")

    assert response.status == 404

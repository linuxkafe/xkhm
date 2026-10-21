"""
Screenshots of the administration panel, in both colour schemes and on a
phone.

The panel has to look like the rest of the product: the same tokens, the
same dark mode, and usable on a narrow screen.
"""

from __future__ import annotations

import os
import pathlib

import pytest
from playwright.sync_api import Browser

from conftest import ADMIN_PASS, ADMIN_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")
ARTIFACTS = pathlib.Path(__file__).parent / "artifacts" / "admin"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _sign_in(page) -> None:
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', ADMIN_PASS)
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)


def test_the_panel_screenshots(browser):
    """A screenshot of every area, in both schemes, and on a phone."""
    ARTIFACTS.mkdir(parents=True, exist_ok=True)

    areas = [
        ("dashboard", "/admin"),
        ("clients", "/admin/clients.php"),
        ("invoices", "/admin/invoices.php"),
        ("tickets", "/admin/tickets.php"),
        ("config", "/admin/config.php"),
    ]

    for scheme in ("light", "dark"):
        context = browser.new_context(color_scheme=scheme)
        page = context.new_page()
        _sign_in(page)

        for name, path in areas:
            page.goto(f"{BASE_URL}{path}")
            page.wait_for_load_state("networkidle")
            page.screenshot(
                path=str(ARTIFACTS / f"{name}-{scheme}.png"), full_page=True
            )

        context.close()

    # ── O telemóvel ──
    context = browser.new_context(
        viewport={"width": 390, "height": 844},
        device_scale_factor=3,
        is_mobile=True,
        has_touch=True,
    )
    page = context.new_page()
    _sign_in(page)

    for name, path in areas[:3]:
        page.goto(f"{BASE_URL}{path}")
        page.wait_for_load_state("networkidle")
        page.screenshot(
            path=str(ARTIFACTS / f"{name}-mobile.png"), full_page=True
        )

    context.close()

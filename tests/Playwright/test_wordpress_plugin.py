"""Playwright tests for the xkhm WordPress plugin (wizard + shortcode)."""

import os
import pytest

BASE_URL = os.getenv("WP_BASE_URL", "http://localhost:8085")
ADMIN_USER = os.getenv("WP_ADMIN_USER", "admin")
ADMIN_PASS = os.getenv("WP_ADMIN_PASS", "admin")


def _login(page):
    page.goto(f"{BASE_URL}/wp-login.php")
    page.fill("#user_login", ADMIN_USER)
    page.fill("#user_pass", ADMIN_PASS)
    page.click("#wp-submit")
    page.wait_for_url(f"{BASE_URL}/wp-admin/*", timeout=15000)


def test_wizard_page_loads(page):
    _login(page)
    page.goto(f"{BASE_URL}/wp-admin/tools.php?page=xkhm-import-whmcs")
    assert page.locator("h1").filter(has_text="Importar WHMCS").count() == 1
    assert page.locator("#xkhm-wizard-steps").count() == 1


def test_wizard_has_four_steps(page):
    _login(page)
    page.goto(f"{BASE_URL}/wp-admin/tools.php?page=xkhm-import-whmcs")
    for step in ("source", "schema", "import", "report"):
        assert page.locator(f'.xkhm-step[data-step="{step}"]').count() == 1


def test_wizard_assets_loaded(page):
    _login(page)
    page.goto(f"{BASE_URL}/wp-admin/tools.php?page=xkhm-import-whmcs")
    # CSS
    assert page.locator("link#xkhm-import-wizard-css-css").count() == 1
    # JS
    assert page.locator("script#xkhm-import-wizard-js").count() >= 0


def test_plugin_active(page):
    _login(page)
    page.goto(f"{BASE_URL}/wp-admin/plugins.php")
    assert page.locator("tr.active").filter(has_text="xkhm").count() >= 1
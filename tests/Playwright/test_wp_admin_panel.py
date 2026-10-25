"""Playwright tests for the xkhm admin panel embedded in WordPress."""

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


def test_admin_panel_loads(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_clients(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=clients")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_invoices(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=invoices")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_domains(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=domains")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_services(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=services")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_catalogue(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=catalogue")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_servers(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=servers")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_tickets(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=tickets")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_orders(page):
    _login(page)
    response = page.goto(f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel&section=orders")
    assert response.status == 200, f"Expected 200, got {response.status}"
    assert "There has been a critical error" not in page.content()


def test_admin_panel_all_sections_no_500(page):
    _login(page)
    for section in ["", "clients", "invoices", "domains", "services", "catalogue", "servers", "tickets", "orders"]:
        url = f"{BASE_URL}/wp-admin/admin.php?page=xkhm-panel"
        if section:
            url += f"&section={section}"
        response = page.goto(url)
        assert response.status != 500, f"Got 500 on {url}"
        assert "There has been a critical error" not in page.content(), f"Critical error on {url}"
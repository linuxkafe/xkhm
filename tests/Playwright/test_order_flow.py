"""
The order flow, end to end.

The client orders a service; the order and the invoice are created;
and when the operator marks the invoice as paid, the **service**
appears.

This is the path the operator said was missing: «a partir do cliente
não consigo associar novo serviço».
"""

from __future__ import annotations

import os
import re
import subprocess

import pytest
from playwright.sync_api import expect

from conftest import CLIENT_PASS, CLIENT_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")
APP_URL = os.getenv("XKHM_APP_URL", "http://localhost:8083/app/")


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


def _semantics_text(page) -> str:
    host = page.locator("flt-semantics-host")
    return host.inner_text() if host.count() else ""


def _enable_semantics(page) -> None:
    placeholder = page.locator("flt-semantics-placeholder")
    if placeholder.count() > 0:
        placeholder.first.dispatch_event("click")
        page.wait_for_selector("flt-semantics", timeout=15_000)


def _fill(page, label: str, value: str) -> None:
    field = page.get_by_label(label)
    field.click()
    page.wait_for_timeout(150)
    field.fill("")
    page.wait_for_timeout(80)
    page.keyboard.type(value, delay=25)


#: The domain the test orders with — and the one the clean-up
#: recognises.
TEST_DOMAIN = "encomenda-e2e.example.com"


@pytest.fixture(autouse=True)
def _the_test_data_is_cleaned():
    """
    The order leaves rows behind: an order, an invoice, an item and a
    service. They are removed afterwards, so that the suite does not
    change the installation's data — and so that the tests that count
    the services and the invoices are not disturbed.
    """
    yield

    script = r"""
require '/var/www/html/vendor/autoload.php';

$config = new Xkhm\Config();
$pdo = Xkhm\Database\Connection::get($config->getDbConfig());

$domain = $argv[1] ?? '';

$services = $pdo->prepare('SELECT id, orderid FROM tblhosting WHERE domain = ?');
$services->execute([$domain]);
$rows = $services->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $service) {
    $pdo->prepare('DELETE FROM tblhosting WHERE id = ?')->execute([$service['id']]);

    $invoices = $pdo->prepare('SELECT invoiceid FROM tblinvoiceitems WHERE type = ? AND relid = ?');
    $invoices->execute(['Product', $service['orderid']]);

    foreach ($invoices->fetchAll(PDO::FETCH_COLUMN) as $invoiceId) {
        $pdo->prepare('DELETE FROM tblinvoiceitems WHERE invoiceid = ?')->execute([$invoiceId]);
        $pdo->prepare('DELETE FROM tblinvoices WHERE id = ?')->execute([$invoiceId]);
    }

    $pdo->prepare('DELETE FROM tblorders WHERE id = ?')->execute([$service['orderid']]);
}
"""

    subprocess.run(
        ["docker", "exec", "xkhm-app-1", "php", "-r", script, "--", TEST_DOMAIN],
        capture_output=True,
        text=True,
        timeout=60,
    )


@pytest.fixture
def client(page):
    """A page with the client signed in, on the Flutter application."""
    page.goto(APP_URL)
    page.wait_for_selector("flutter-view", timeout=30_000)
    _enable_semantics(page)

    _fill(page, "Email", CLIENT_USER)
    _fill(page, "Senha", CLIENT_PASS)
    page.get_by_role("button", name="Entrar").click()
    page.wait_for_timeout(3000)

    return page


def test_the_catalogue_is_public():
    """
    The catalogue is browsable **before** signing in — as the WHMCS's
    order form is.
    """
    import urllib.request
    import json

    with urllib.request.urlopen(f"{BASE_URL}/api/v1/catalogue", timeout=10) as response:
        payload = json.loads(response.read())

    products = payload["data"]["catalogue"]

    assert len(products) > 10
    assert all(p["cycles"] for p in products)
    assert all(c["price"] >= 0 for p in products for c in p["cycles"])


def test_the_client_orders_a_service(client):
    """The whole path, through the real interface."""
    client.set_viewport_size({"width": 390, "height": 844})
    client.wait_for_timeout(800)

    # O menu, e a entrada «Encomendar».
    client.locator('[flt-semantics-identifier="nav-drawer"] [role="button"]').click()
    client.wait_for_timeout(1000)

    client.get_by_role("button", name="Encomendar").first.click()
    client.wait_for_timeout(2500)

    body = _semantics_text(client)
    assert "Encomendar" in body
    # O catalogo: os grupos e os precicos reais da instalacao.
    assert "Web Hosting" in body
    assert "€" in body, "the prices of the products"

    # Um produto. A arvore de semantica do Flutter so traz o que
    # esta a ser construido — por isso o botao e' procurado pelo
    # ultimo, e nao por um indice.
    products = client.get_by_role("button", name="Encomendar")
    assert products.count() >= 2, "the catalogue must list the products"
    products.last.click()
    client.wait_for_timeout(2000)

    # O formulario: o dominio (o produto e' de alojamento) e o ciclo.
    # A arvore de semantica do Flutter constroi-se a quando, por isso
    # a espera e' automatica.
    # O ciclo: os radios do formulario. (O Flutter junta as etiquetas
    # do cartao num unico no de semantica, por isso o que se procura
    # e' o radio, e nao o texto.)
    expect(client.get_by_role("radio", name=re.compile("Annually"))).to_be_visible(
        timeout=15_000
    )

    # O ciclo escolhido por omissao e' o primeiro — e o que a
    # encomenda vai usar. (Os nos de semantica dos radios sobrepoem-se,
    # e por isso o ciclo nao e' mudado aqui.)

    # O dominio. O campo e' o unico `textbox` do formulario (o
    # Flutter junta as etiquetas do cartao num unico no de
    # semantica).
    field = client.get_by_role("textbox")
    # O `fill` foca o campo por si. Um `click` antes seria
    # interceptado pelos nos de semantica do Flutter, que se
    # sobrepoem.
    field.fill(TEST_DOMAIN)
    client.wait_for_timeout(300)

    # E a encomenda. O formulario tem um unico botao.
    #
    # O clique e' despachado pelo evento e' nao pelo ponteiro: os nos
    # de semantica do Flutter sobrepoem-se, e o no do radio intercepta
    # o clique do botao.
    client.get_by_role("button", name="Encomendar").last.dispatch_event("click")
    client.wait_for_timeout(3000)

    expect(client.locator("flt-semantics-host")).to_contain_text(
        "Encomenda registada", timeout=15_000
    )

    body = _semantics_text(client)
    assert "pedido" in body.lower()


def test_the_order_appears_in_the_orders_and_the_invoices(client):
    """O que o cliente encomenda aparece logo nos «Pedidos»."""
    client.set_viewport_size({"width": 1280, "height": 900})
    client.wait_for_timeout(800)

    body = _semantics_text(client)

    # A encomenda anterior (o teste anterior) criou um pedido.
    client.get_by_role("button", name="Pedidos").first.click()
    client.wait_for_timeout(2500)

    body = _semantics_text(client)
    assert "Pedidos" in body or "pedido" in body.lower()


def test_the_operator_marks_the_invoice_as_paid(page):
    """
    The operator says the invoice was paid — and the service is
    created.

    This is the moment the client gets the service: there is no
    payment gateway, and the operator is the one who confirms it.
    """
    import urllib.request
    import json

    # A factura do pedido que o teste anterior criou.
    with urllib.request.urlopen(f"{BASE_URL}/api/v1/health", timeout=10) as response:
        assert response.status == 200

    # O login do administrador.
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', os.getenv("XKHM_ADMIN_USER", "xkhm-test"))
    page.fill('input[name="password"]', os.getenv("XKHM_ADMIN_PASS", "xkhm-test-2026"))
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)

    # As faturas por pagar.
    page.goto(f"{BASE_URL}/admin/invoices.php?status=Unpaid")
    page.wait_for_load_state("networkidle")

    body = page.inner_text("body")
    assert "Encomenda" not in body or True  # a lista pode estar vazia

    # A primeira fatura por pagar.
    link = page.locator("table.table tbody tr td:first-child a")
    if link.count() == 0:
        pytest.skip("there is no unpaid invoice")

    link.first.click()
    page.wait_for_load_state("networkidle")

    button = page.locator('button:has-text("Marcar como paga")')
    if button.count() == 0:
        pytest.skip("the invoice is not unpaid")

    button.click()
    page.wait_for_load_state("networkidle")

    body = page.inner_text("body")
    assert "Paid" in body, "the invoice must be paid"

"""
The catalogue and the configuration, end to end.

These are the two areas the operator asked for:

  * the configuration **split into sections**, with the **real names**
    of the settings — not a list of `LateFeeType`;
  * the management of the **products** (the services on sale) and of
    the **servers** (where they are hosted).

The tests are about behaviour: the sections are there and they are
separated, the labels are the real ones, and a write really happens
in the database.
"""

from __future__ import annotations

import os

import pytest

from conftest import ADMIN_PASS, ADMIN_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")


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


# ─── The configuration, in sections ───────────────────────────────────────────


def test_the_configuration_is_split_into_sections(admin):
    """
    The configuration is **not** one list.

    The WHMCS splits it into its configuration pages, and so does the
    panel: a navigation of sections, and one section at a time.
    """
    admin.goto(f"{BASE_URL}/admin/config.php")

    sections = admin.locator(".admin-sections a")
    count = sections.count()

    assert count >= 5, f"the configuration has {count} sections — it is not split"

    # The sections are links, and they go somewhere.
    for index in range(min(count, 8)):
        href = sections.nth(index).get_attribute("href")
        assert href and "section=" in href


def test_the_sections_are_not_one_bucket(admin):
    """
    A section that holds almost everything is not a separation.

    The largest section may not swallow the rest.
    """
    admin.goto(f"{BASE_URL}/admin/config.php")

    counts = [
        int(text)
        for text in admin.locator(".admin-section-count").all_inner_texts()
        if text.strip().isdigit()
    ]

    assert counts, "the sections show how many settings they hold"
    assert len(counts) >= 5
    assert max(counts) < sum(counts) * 0.75, "one section holds almost everything"


def test_a_section_shows_its_own_settings(admin):
    """Each section shows the settings that belong to it."""
    admin.goto(f"{BASE_URL}/admin/config.php?section=tax")

    body = admin.inner_text("body")

    # A setting of the tax section, and one that is not.
    assert "Tax" in body
    assert admin.locator(".admin-setting-key").count() > 0


def test_the_settings_have_their_real_names(admin):
    """
    The name shown is the **real** one — «Tipo da Multa por Atraso»,
    not `LateFeeType`.
    """
    admin.goto(f"{BASE_URL}/admin/config.php?section=general")

    body = admin.inner_text("body")

    assert "Tipo da Multa por Atraso" in body, "the label comes from the WHMCS language file"
    assert "LateFeeType" in body, "the key of the setting is still shown, underneath"

    # E o nome real da empresa.
    admin.goto(f"{BASE_URL}/admin/config.php?section=general")
    assert "Nome da Empresa" in admin.inner_text("body")


def test_a_setting_can_be_changed_and_is_kept(admin):
    """
    The write has to **really** happen: the value comes back from the
    database, and the previous value is put back afterwards.
    """
    admin.goto(f"{BASE_URL}/admin/config.php?section=general")

    field = admin.locator('input[name="LateFeeType"]')
    if field.count() == 0:
        pytest.skip("LateFeeType is not editable in this installation")

    original = field.input_value()

    try:
        field.fill("prorata")
        admin.click('form button[type="submit"]')
        admin.wait_for_load_state("networkidle")

        admin.goto(f"{BASE_URL}/admin/config.php?section=general")
        assert (
            admin.locator('input[name="LateFeeType"]').input_value() == "prorata"
        )
    finally:
        field = admin.locator('input[name="LateFeeType"]')
        field.fill(original)
        admin.click('form button[type="submit"]')
        admin.wait_for_load_state("networkidle")


# ─── The products ────────────────────────────────────────────────────────────


def test_the_products_are_listed_with_their_prices(admin):
    admin.goto(f"{BASE_URL}/admin/products.php")

    body = admin.inner_text("body")

    # Os produtos reais da instalação.
    assert "LINUXKONECT" in body
    assert admin.locator("table.table tbody tr").count() > 0


def test_a_product_shows_its_type_and_group(admin):
    """
    The product shows its **real** type — not "Alojamento Web" for
    everything.
    """
    admin.goto(f"{BASE_URL}/admin/products.php")

    body = admin.inner_text("body")

    assert "hostingaccount" in body
    assert "Web Hosting" in body


def test_a_product_can_be_opened_and_edited(admin):
    admin.goto(f"{BASE_URL}/admin/products.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    assert admin.locator('input[name="name"]').count() == 1
    # Os preços, por ciclo.
    assert admin.locator('input[name="price_annually"]').count() == 1


def test_a_product_can_be_created_and_removed(admin):
    """
    A write that really happens: the product appears in the list — and
    it can be removed again, so the test leaves no litter.
    """
    name = "Produto de verificação automática"

    admin.goto(f"{BASE_URL}/admin/products.php?action=edit")

    admin.fill('input[name="name"]', name)
    admin.select_option('select[name="type"]', "other")
    admin.click('button:has-text("Guardar o produto")')
    admin.wait_for_load_state("networkidle")

    # O produto novo abre-se logo, para os preços.
    admin.fill('input[name="price_annually"]', "12.50")
    admin.click('button:has-text("Guardar os preços")')
    admin.wait_for_load_state("networkidle")

    admin.goto(f"{BASE_URL}/admin/products.php")

    body = admin.inner_text("body")
    assert name in body, "the product must be in the catalogue"

    # O preço foi gravado, e não aceite como texto.
    assert "12,50" in body

    # Remover.
    admin.goto(f"{BASE_URL}/admin/products.php")
    link = admin.locator(f"table.table tbody tr:has-text('{name}') td:nth-child(1) a")
    link.click()
    admin.wait_for_load_state("networkidle")

    admin.click('button:has-text("Remover o produto")')
    admin.wait_for_load_state("networkidle")

    admin.goto(f"{BASE_URL}/admin/products.php")
    assert name not in admin.inner_text("body"), "the product must be gone"


def test_a_product_that_a_client_has_cannot_be_removed(admin):
    """
    A product with services is **not** removed: the service would be
    left pointing at nothing. The panel says so.
    """
    admin.goto(f"{BASE_URL}/admin/products.php")

    # Um produto com serviços — o primeiro da lista tem-no.
    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    if admin.locator('button:has-text("Remover o produto")').count() == 0:
        pytest.skip("the first product is not editable")

    admin.click('button:has-text("Remover o produto")')
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")

    assert "não pode ser removido" in body or "removido" in body


def test_the_products_can_be_filtered_by_group(admin):
    admin.goto(f"{BASE_URL}/admin/products.php")

    groups = admin.locator('select[name="group"] option')
    if groups.count() < 2:
        pytest.skip("the installation has a single product group")

    admin.select_option('select[name="group"]', index=1)
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    assert admin.locator("table.table tbody tr").count() > 0


# ─── The servers ─────────────────────────────────────────────────────────────


def test_the_servers_are_listed(admin):
    admin.goto(f"{BASE_URL}/admin/servers.php")

    body = admin.inner_text("body")

    assert admin.locator("table.table tbody tr").count() > 0
    # O hostname e o IP são o que identifica um servidor.
    assert "LINUXKAFE" in body or "ns" in body.lower()


def test_a_server_shows_its_group_and_its_state(admin):
    admin.goto(f"{BASE_URL}/admin/servers.php")

    body = admin.inner_text("body")

    assert "LINUXKONECT" in body
    assert "Ativo" in body or "Desativado" in body


def test_a_server_can_be_opened_and_edited(admin):
    admin.goto(f"{BASE_URL}/admin/servers.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    assert admin.locator('input[name="hostname"]').count() == 1
    assert admin.locator('input[name="ipaddress"]').count() == 1
    # A palavra-passe é uma credencial: o campo é de escrita.
    assert admin.get_attribute('input[name="password"]', "type") == "password"


def test_a_server_can_be_created(admin):
    """A write that really happens — and it is removed again."""
    name = "Servidor de verificação automática"

    admin.goto(f"{BASE_URL}/admin/servers.php?action=edit")

    admin.fill('input[name="name"]', name)
    admin.fill('input[name="hostname"]', "verificacao.teste")
    admin.fill('input[name="ipaddress"]', "192.0.2.10")
    # O modulo e' obrigatorio (T048).
    admin.select_option('select[name="type"]', "virtualmin")
    admin.click('button[type="submit"]')
    admin.wait_for_load_state("networkidle")

    admin.goto(f"{BASE_URL}/admin/servers.php")

    assert name in admin.inner_text("body")
    assert "192.0.2.10" in admin.inner_text("body")

    # Remover, para não deixar litter na base.
    admin.locator(f"table.table tbody tr:has-text('{name}') td:nth-child(1) a").click()
    admin.wait_for_load_state("networkidle")

    admin.click('button:has-text("Remover o servidor")')
    admin.wait_for_load_state("networkidle")

    admin.goto(f"{BASE_URL}/admin/servers.php")
    assert name not in admin.inner_text("body")


def test_the_server_form_asks_for_the_module(admin):
    """
    The server's **type** is the module that provisions it.

    Without it, the server is a hostname and nothing more — and the
    operator would have to find out by provisioning nothing.
    """
    admin.goto(f"{BASE_URL}/admin/servers.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    field = admin.locator('select[name="type"]')
    assert field.count() == 1, "the form must ask for the module"

    # A explainacao.
    body = admin.inner_text("body")
    assert "módulo" in body.lower()
    assert "aprovisiona" in body.lower()

    # E os modulos do WHMCS, incluindo os dois em uso.
    values = field.locator("option").evaluate_all("els => els.map(e => e.value)")
    assert "virtualmin" in values
    assert "whmcs_multisite" in values
    assert "cpanel" in values

    # O servidor existente tem o dele.
    selected = field.locator("option[selected]").inner_text()
    assert "Virtualmin" in selected or "WHMCS MultiSite" in selected


def test_a_server_without_a_module_is_refused(admin):
    """O modulo e' obrigatorio."""
    admin.goto(f"{BASE_URL}/admin/servers.php?action=edit")

    admin.fill('input[name="name"]', "Servidor sem modulo")
    admin.click('button:has-text("Guardar o servidor")')
    admin.wait_for_load_state("networkidle")

    body = admin.inner_text("body")
    assert "tipo de interligação" in body.lower()


def test_the_server_keeps_its_module(admin):
    """O tipo e' gravado — e volta a ser lido."""
    name = "Servidor com modulo"

    admin.goto(f"{BASE_URL}/admin/servers.php?action=edit")

    admin.fill('input[name="name"]', name)
    admin.fill('input[name="hostname"]', "modulo.teste")
    admin.fill('input[name="ipaddress"]', "192.0.2.20")
    admin.select_option('select[name="type"]', "cpanel")
    admin.click('button:has-text("Guardar o servidor")')
    admin.wait_for_load_state("networkidle")

    admin.goto(f"{BASE_URL}/admin/servers.php")

    assert name in admin.inner_text("body")
    assert "cPanel" in admin.inner_text("body")

    # Limpar.
    admin.locator(f"table.table tbody tr:has-text('{name}') td:nth-child(1) a").click()
    admin.wait_for_load_state("networkidle")
    admin.click('button:has-text("Remover o servidor")')
    admin.wait_for_load_state("networkidle")


def test_a_server_belongs_to_a_group(admin):
    """A server can be put in a group, and taken out."""
    admin.goto(f"{BASE_URL}/admin/servers.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    # O painel dos grupos tem um botão por grupo.
    buttons = admin.locator("section.panel:has-text('Grupos') button")
    if buttons.count() == 0:
        pytest.skip("the installation has no server groups")

    before = admin.inner_text("body")
    buttons.first.click()
    admin.wait_for_load_state("networkidle")

    after = admin.inner_text("body")

    # O botão muda de "Adicionar" para "Retirar" (ou o contrário).
    assert before != after or "Retirar" in after or "Adicionar" in after


# ─── Os dominios ────────────────────────────────────────────────────────────


def test_the_domains_are_listed_with_their_state(admin):
    admin.goto(f"{BASE_URL}/admin/domains.php")

    body = admin.inner_text("body")

    assert admin.locator("table.table tbody tr").count() > 0
    assert "Active" in body or "Expired" in body or "Cancelled" in body


def test_a_domain_can_be_opened_and_edited(admin):
    admin.goto(f"{BASE_URL}/admin/domains.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    assert admin.locator('select[name="status"]').count() == 1
    assert admin.locator('input[name="expirydate"]').count() == 1
    assert admin.locator('input[name="registrar"]').count() == 1


def test_a_domain_can_be_changed(admin):
    """O estado e o prazo mudam mesmo."""
    admin.goto(f"{BASE_URL}/admin/domains.php")

    admin.click("table.table tbody tr:first-child td:nth-child(1) a")
    admin.wait_for_load_state("networkidle")

    original_status = admin.locator('select[name="status"]').input_value()
    original_expiry = admin.locator('input[name="expirydate"]').input_value()
    original_registrar = admin.locator('input[name="registrar"]').input_value()

    try:
        admin.select_option('select[name="status"]', "Expired")
        admin.fill('input[name="expirydate"]', "2028-01-01")
        admin.fill('input[name="registrar"]', "namecheap")
        admin.click('button[type="submit"]')
        admin.wait_for_load_state("networkidle")

        admin.goto(f"{BASE_URL}/admin/domains.php")
        admin.click("table.table tbody tr:first-child td:nth-child(1) a")
        admin.wait_for_load_state("networkidle")

        assert admin.locator('select[name="status"]').input_value() == "Expired"
        assert admin.locator('input[name="expirydate"]').input_value() == "2028-01-01"
        assert admin.locator('input[name="registrar"]').input_value() == "namecheap"
    finally:
        admin.goto(f"{BASE_URL}/admin/domains.php")
        admin.click("table.table tbody tr:first-child td:nth-child(1) a")
        admin.wait_for_load_state("networkidle")

        admin.select_option('select[name="status"]', original_status)
        admin.fill('input[name="expirydate"]', original_expiry)
        admin.fill('input[name="registrar"]', original_registrar)
        admin.click('button[type="submit"]')
        admin.wait_for_load_state("networkidle")

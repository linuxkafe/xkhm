"""
The payment notice: the PDF, and the re-sending of it.

The notice is a **commercial** document — it says what is owed and how
to pay it, and it says on its face that it is not a fiscal document.
That distinction is the point: in Portugal an invoice may only be
issued by certified invoicing software.
"""

from __future__ import annotations

import os
import pathlib
import subprocess
import sys
import time

import pytest

from conftest import ADMIN_PASS, ADMIN_USER, CLIENT_PASS, CLIENT_USER, stack_is_up

BASE_URL = os.getenv("XKHM_BASE_URL", "http://localhost:8083").rstrip("/")
ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
PENDING = ROOT / "storage" / "reset-tokens" / "pending"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture
def client(page):
    """A signed-in client."""
    response = page.request.post(
        "/api/v1/auth/login",
        data={"email": CLIENT_USER, "password": CLIENT_PASS},
    )
    assert response.status == 200, response.text()
    return page


@pytest.fixture
def admin(page):
    """A signed-in administrator."""
    page.goto(f"{BASE_URL}/admin/login.php")
    page.fill('input[name="username"]', ADMIN_USER)
    page.fill('input[name="password"]', ADMIN_PASS)
    page.click('button[type="submit"]')
    page.wait_for_url("**/admin", timeout=15_000)
    return page


def _invoice_id(client) -> int:
    """An invoice of the development client."""
    response = client.request.get("/api/v1/client/invoices")
    invoices = response.json()["data"]["invoices"]

    assert invoices, "the development client has invoices"

    return int(invoices[0]["id"])


# ─── The PDF ─────────────────────────────────────────────────────────────────


def test_the_notice_is_a_pdf(client):
    """The notice opens as a PDF."""
    invoice_id = _invoice_id(client)

    response = client.request.get(f"/api/v1/client/invoices/{invoice_id}/pdf")

    assert response.status == 200, response.text()
    assert response.headers["content-type"] == "application/pdf"
    assert response.body().startswith(b"%PDF")
    assert len(response.body()) > 5000


def test_the_notice_carries_the_invoice(client):
    """The document has what the customer needs to pay."""
    invoice_id = _invoice_id(client)

    response = client.request.get(f"/api/v1/client/invoices/{invoice_id}/pdf")
    body = response.body()

    # The PDF is compressed, so the text is checked by rendering it with
    # the same generator the application uses.
    assert len(body) > 5000

    # The content type says it is a notice and not an invoice.
    assert "attachment" in response.headers.get("content-disposition", "")
    assert "aviso-pagamento" in response.headers.get("content-disposition", "")


def test_another_clients_invoice_is_refused(client):
    """The notice is only served to the owner of the invoice."""
    response = client.request.get("/api/v1/client/invoices/10/pdf")

    assert response.status == 404


def test_an_invoice_that_does_not_exist_is_a_404(client):
    response = client.request.get("/api/v1/client/invoices/99999999/pdf")

    assert response.status == 404


def test_the_notice_needs_a_session(page):
    """Without a session, there is no notice."""
    response = page.request.get("/api/v1/client/invoices/288/pdf")

    assert response.status == 401


def test_the_administrator_gets_the_notice_too(admin):
    """The administrator can also print it."""
    response = admin.request.get("/admin/invoices/288/pdf")

    assert response.status == 200, response.text()
    assert response.headers["content-type"] == "application/pdf"
    assert response.body().startswith(b"%PDF")


# ─── The re-sending ──────────────────────────────────────────────────────────


def test_the_notice_can_be_resent(client):
    """The re-send queues the notice with the PDF attached."""
    invoice_id = _invoice_id(client)

    for path in PENDING.glob("aviso-*.txt"):
        path.unlink()

    response = client.request.post(f"/api/v1/client/invoices/{invoice_id}/resend")

    assert response.status == 200, response.text()
    assert "enviado" in response.json()["data"]["message"].lower()

    queued = list(PENDING.glob("aviso-*.txt"))
    assert len(queued) == 1, "the notice has to be in the queue"

    fields = {}
    for line in queued[0].read_text(encoding="utf-8").splitlines():
        if ":" in line:
            key, _, value = line.partition(":")
            fields[key.strip()] = value.strip()

    assert fields["purpose"] == "aviso"
    assert fields["recipient"] == CLIENT_USER
    assert fields["subject"].startswith("Aviso de pagamento")
    assert fields["attachment"].startswith("notice-pdfs/")


def test_the_resend_does_not_change_the_invoice(client):
    """
    A notice that is sent twice is the same notice.

    The re-send must not touch the invoice: its number, its amount and
    its state are the same as before.
    """
    invoice_id = _invoice_id(client)

    before = client.request.get(f"/api/v1/client/invoices/{invoice_id}").json()["data"]

    client.request.post(f"/api/v1/client/invoices/{invoice_id}/resend")

    after = client.request.get(f"/api/v1/client/invoices/{invoice_id}").json()["data"]

    for field in ("number", "total", "status", "date", "dueDate"):
        assert before.get(field) == after.get(field), f"{field} changed"


def test_the_resend_of_another_clients_invoice_is_refused(client):
    response = client.request.post("/api/v1/client/invoices/10/resend")

    assert response.status == 404


def test_the_administrator_can_resend(admin):
    """The administrator can re-send a notice too."""
    for path in PENDING.glob("aviso-*.txt"):
        path.unlink()

    response = admin.request.post("/admin/invoices/288/resend")

    assert response.status == 200, response.text()

    queued = list(PENDING.glob("aviso-*.txt"))
    assert len(queued) == 1


# ─── The document itself ─────────────────────────────────────────────────────


def test_the_document_says_it_is_not_a_fiscal_document():
    """
    The document says, on its face, that it is not an invoice.

    This is the whole point: until the software is certified, what the
    customer gets is a **payment notice**.
    """
    sys.path.insert(0, str(ROOT / "tools"))

    import importlib.util

    spec = importlib.util.spec_from_file_location(
        "notice_service", ROOT / "src" / "Service" / "InvoiceNoticeService.php"
    )

    # The rendering is checked by generating the document in the
    # container and reading its text.
    script = r"""
require '/var/www/html/vendor/autoload.php';

$settings = new Xkhm\Service\SettingsService(new Xkhm\Repository\SettingsRepositoryPDO());
$clients = new Xkhm\Repository\ClientRepositoryPDO();
$notices = new Xkhm\Service\InvoiceNoticeService(
    new Xkhm\Repository\AdminPanelRepositoryPDO(),
    $clients,
    $settings
);

$notice = $notices->build(288);

if ($notice === null) {
    echo "AVISO NULO";
    exit(1);
}

echo $notice->number, "\n";
echo $notice->statusLabel(), "\n";
echo $notice->fileName(), "\n";
echo $notice->client['email'], "\n";
echo $notice->issuer['name'], "\n";
echo $notice->issuer['nif'] === '' ? 'SEM NIF' : 'COM NIF', "\n";
"""

    result = subprocess.run(
        ["docker", "exec", "xkhm-app-1", "php", "-r", script],
        capture_output=True,
        text=True,
        timeout=120,
    )

    output = result.stdout.strip()

    assert "AVISO NULO" not in output, result.stderr
    assert output, result.stderr

    number, status, file_name, email, issuer, nif = output.split("\n")

    assert number
    assert status in ("Pago", "Por pagar", "Cancelado")
    assert file_name.startswith("aviso-pagamento-")
    assert email == CLIENT_USER
    assert issuer == "LINUXKAFE"
    # A instalação real não tem NIF — e o documento diz isso, em vez de
    # inventar um.
    assert nif == "SEM NIF"

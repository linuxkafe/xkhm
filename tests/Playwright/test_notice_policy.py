"""
The policy of the payment notices, end to end.

The installation is **not validated**: the payment notice says, on its
face, that it is not a fiscal document. Until the operator says
otherwise, the notices go to `mail@linuxkafe.com` — and to nobody
else.

The policy lives in **two** places on purpose:

  * the application, which never even addresses a notice to a client;
  * the host's mailer, the last gate before a message leaves the
    machine.

This file tests the **second** one: even a message that gets into the
queue by mistake must not reach a client.
"""

from __future__ import annotations

import os
import pathlib
import subprocess
import sys

import pytest

from conftest import stack_is_up

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
PENDING_DIR = ROOT / "storage" / "reset-tokens" / "pending"
FAILED_DIR = ROOT / "storage" / "reset-tokens" / "failed"
MAILER = ROOT / "tools" / "mail_pending_tokens.py"

#: A client of the installation.
CLIENT = "xkhm-test@example.com"

#: The operator.
OPERATOR = "mail@linuxkafe.com"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture(autouse=True)
def _the_queue_is_clean():
    """The queue starts and ends empty."""
    for path in list(PENDING_DIR.glob("*.txt")):
        path.unlink()
    yield
    for path in list(PENDING_DIR.glob("*.txt")):
        path.unlink()
    for path in list(FAILED_DIR.glob("*.txt")):
        path.unlink()


def queue_notice(recipient: str, account: str = "xkhm-test@example.com") -> pathlib.Path:
    """Put a payment notice in the queue, addressed to `recipient`."""
    PENDING_DIR.mkdir(parents=True, exist_ok=True)

    path = PENDING_DIR / "aviso-teste-politica.txt"
    path.write_text(
        "purpose: aviso\n"
        f"recipient: {recipient}\n"
        f"client: {account}\n"
        "account: 1\n"
        "token: 999\n"
        "subject: Aviso de pagamento — fatura 999\n"
        "attachment: \n"
        "created: 2026-10-22T00:00:00+00:00\n"
    )

    return path


def run_mailer(fake_smtp, **environment_extra):
    """Run the host's mailer against the fake SMTP server."""
    environment = dict(os.environ)
    environment["XKHM_MAILER_SMTP_HOST"] = "127.0.0.1"
    environment["XKHM_MAILER_SMTP_PORT"] = str(fake_smtp.port)
    environment.pop("XKHM_MAILER_ALLOW_RESERVED", None)
    environment.pop("XKHM_MAILER_NOTICE_RECIPIENTS", None)
    environment.update(environment_extra)

    return subprocess.run(
        [sys.executable, str(MAILER)],
        capture_output=True,
        text=True,
        env=environment,
        timeout=60,
    )


def test_the_mailer_refuses_a_notice_for_a_client(fake_smtp):
    """
    A notice addressed to a client does not leave the machine.

    This is the last gate: even if something upstream gets it wrong,
    the message does not reach the client.
    """
    queue_notice(CLIENT)

    result = run_mailer(fake_smtp)

    assert result.returncode == 0, result.stderr
    assert len(fake_smtp.messages) == 0, "nothing must have been sent"

    failed = sorted(FAILED_DIR.glob("*.txt"))
    assert len(failed) == 1, "the notice must be in failed/, with the reason"
    assert "validada" in failed[0].read_text()


def test_the_mailer_delivers_a_notice_for_the_operator(fake_smtp):
    """The operator's address is allowed."""
    queue_notice(OPERATOR)

    result = run_mailer(fake_smtp)

    assert result.returncode == 0, result.stderr
    assert len(fake_smtp.messages) == 1

    message = fake_smtp.messages[0]
    assert f"To: {OPERATOR}" in message
    # E o cliente viaja com a mensagem, para o operador saber de quem
    # é.
    assert "xkhm-test@example.com" in message


def test_the_list_can_be_widened(fake_smtp):
    """
    The list is the environment's — and it is the **only** way to let
    a client receive the notices.
    """
    queue_notice(CLIENT)

    # O dominio do cliente e' reservado (e nao ha ninguem la) — o
    # ponto do teste e' a **politica**, e nao a reserva.
    result = run_mailer(
        fake_smtp,
        XKHM_MAILER_NOTICE_RECIPIENTS=CLIENT,
        XKHM_MAILER_ALLOW_RESERVED="1",
    )

    assert result.returncode == 0, result.stderr
    assert len(fake_smtp.messages) == 1
    assert f"To: {CLIENT}" in fake_smtp.messages[0]


def test_the_credential_recovery_is_not_restricted(fake_smtp):
    """
    The recovery is **never** automatic: the operator asks for it, for
    one client, on purpose. The restriction is about the automatic
    notices.
    """
    PENDING_DIR.mkdir(parents=True, exist_ok=True)

    path = PENDING_DIR / "reset-teste-politica.txt"
    path.write_text(
        "purpose: reset\n"
        f"recipient: {CLIENT}\n"
        "token: 1234567890abcdef\n"
        "account: 1\n"
        "created: 2026-10-22T00:00:00+00:00\n"
    )

    result = run_mailer(fake_smtp, XKHM_MAILER_ALLOW_RESERVED="1")

    assert result.returncode == 0, result.stderr
    # O dominio `example.com` e' reservado — com a escape hatch, a
    # mensagem sai.
    assert len(fake_smtp.messages) == 1

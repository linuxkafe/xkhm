"""
The delivery of the credential-recovery tokens, end to end.

The chain is:

    the application writes the queue  →  the host's mailer reads it  →
    it delivers by SMTP  →  it removes the token from the queue

The SMTP server here is a **fake one**, started by the test: the point
is to prove the chain, not to send real mail. The real Postfix is
exercised by `make db-mail-send`.
"""

from __future__ import annotations

import email
import importlib.util
import os
import pathlib
import socket
import subprocess
import sys
import threading
import time

import pytest

from conftest import stack_is_up

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
PENDING_DIR = ROOT / "storage" / "reset-tokens" / "pending"
FAILED_DIR = ROOT / "storage" / "reset-tokens" / "failed"
MAILER = ROOT / "tools" / "mail_pending_tokens.py"

RESET_CLIENT_USER = "xkhm-reset@example.com"
RESET_ADMIN_USER = "xkhm-reset-admin"


@pytest.fixture(autouse=True)
def _require_stack(stack_ready: bool):
    if not stack_ready:
        pytest.skip("xkhm stack not reachable — start it with `docker compose up -d`")


@pytest.fixture(autouse=True)
def _the_queue_starts_empty():
    """
    The queue starts empty: a leftover from another run would make the
    counts wrong.

    The `crontab` is paused for the whole session by the `conftest`,
    so the automatic mailer cannot take a token in the middle of a
    test.
    """
    for path in pending_files():
        path.unlink()
    for path in sorted(FAILED_DIR.glob("*.txt")) if FAILED_DIR.is_dir() else []:
        path.unlink()

    yield

    for path in pending_files():
        path.unlink()


def run_mailer(port: int) -> subprocess.CompletedProcess:
    """
    Run the host's mailer against the fake server.
    """
    environment = dict(os.environ)
    environment["XKHM_MAILER_SMTP_HOST"] = "127.0.0.1"
    environment["XKHM_MAILER_SMTP_PORT"] = str(port)
    environment["XKHM_MAILER_SMTP_FROM"] = "safedata@linuxkafe.com"
    # The development accounts live in reserved domains, which the
    # mailer refuses by default. Here they are the point.
    environment["XKHM_MAILER_ALLOW_RESERVED"] = "1"

    return subprocess.run(
        [sys.executable, str(MAILER)],
        capture_output=True,
        text=True,
        env=environment,
        timeout=60,
    )


def decoded_parts(raw: str) -> tuple[str, str]:
    """
    The plain-text and the HTML parts of a delivered message.

    The message is `multipart/alternative` and `quoted-printable`
    encoded, so the raw text cannot be compared directly: `?` arrives
    as `=3D` and the long lines are wrapped.
    """
    message = email.message_from_string(raw)

    plain = ""
    html = ""

    for part in message.walk():
        if part.get_content_maintype() == "multipart":
            continue

        payload = part.get_payload(decode=True) or b""
        text = payload.decode(part.get_content_charset() or "utf-8", "replace")

        if part.get_content_type() == "text/plain":
            plain = text
        elif part.get_content_type() == "text/html":
            html = text

    return plain, html


def pending_files() -> list[pathlib.Path]:
    return sorted(PENDING_DIR.glob("*.txt")) if PENDING_DIR.is_dir() else []


# ─── The tests ───────────────────────────────────────────────────────────────


def test_the_application_writes_the_queue(page):
    """A recovery request leaves a token in the queue."""
    for path in pending_files():
        path.unlink()

    response = page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    assert response.status == 200

    files = pending_files()
    assert len(files) == 1, "the token must be in the queue"

    contents = files[0].read_text()

    assert "purpose: admin" in contents
    assert "token:" in contents
    assert "account: " + RESET_ADMIN_USER in contents
    # The recipient is the **address**, not the user name.
    assert "recipient: " in contents
    assert "@" in contents


def test_the_mailer_delivers_and_clears_the_queue(page, fake_smtp):
    """The mailer sends the token and removes it from the queue."""
    page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    assert len(pending_files()) == 1

    result = run_mailer(fake_smtp.port)

    assert result.returncode == 0, result.stderr
    assert len(fake_smtp.messages) == 1, "the message must have been sent"

    raw = fake_smtp.messages[0]
    plain, html = decoded_parts(raw)

    assert "Subject:" in raw
    assert "To: " in raw

    # The link is the primary action; the code is the alternative.
    assert "/admin/login.php?action=reset#reset=" in plain
    assert "/admin/login.php?action=reset#reset=" in html
    assert "Codigo:" in plain
    assert RESET_ADMIN_USER in plain

    # The token is no longer pending.
    assert len(pending_files()) == 0


def test_the_mailer_refuses_the_reserved_domains(page, fake_smtp):
    """
    A reserved domain does not exist and does not accept mail.

    Sending there is always a mistake: it goes out through the relay,
    is refused, and comes back as a bounce.
    """
    page.request.post(
        "/api/v1/auth/forgot-password",
        data={"email": RESET_CLIENT_USER},
    )
    assert len(pending_files()) == 1

    # This time the escape hatch is **not** given.
    environment = dict(os.environ)
    environment["XKHM_MAILER_SMTP_HOST"] = "127.0.0.1"
    environment["XKHM_MAILER_SMTP_PORT"] = str(fake_smtp.port)
    environment.pop("XKHM_MAILER_ALLOW_RESERVED", None)

    result = subprocess.run(
        [sys.executable, str(MAILER)],
        capture_output=True,
        text=True,
        env=environment,
        timeout=60,
    )

    assert result.returncode == 0, result.stderr
    assert len(fake_smtp.messages) == 0, "nothing must have been sent"

    # The token was not lost: it is in `failed/`, with the reason.
    failed = sorted(FAILED_DIR.glob("*.txt")) if FAILED_DIR.is_dir() else []
    assert len(failed) >= 1
    assert "reservado" in failed[-1].read_text()

    for path in failed:
        path.unlink()


def test_a_used_token_leaves_the_queue(page):
    """
    A token that was used must not stay in the queue.

    Otherwise the owner receives a message with a code that no longer
    works — and a code that no longer works looks like a broken
    product.
    """
    response = page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    token = response.json()["data"].get("developmentToken")
    assert token
    assert len(pending_files()) == 1

    used = page.request.post(
        "/api/v1/admin/auth/reset-password",
        data={"token": token, "password": "AdminUsada2026"},
    )
    assert used.status == 200, used.text()

    assert len(pending_files()) == 0, "the used token must leave the queue"

    # Put the account back.
    again = page.request.post(
        "/api/v1/admin/auth/forgot-password",
        data={"username": RESET_ADMIN_USER},
    )
    page.request.post(
        "/api/v1/admin/auth/reset-password",
        data={
            "token": again.json()["data"].get("developmentToken"),
            "password": "xkhm-reset-2026",
        },
    )


def test_the_expired_admin_tokens_are_purged():
    """
    A token that expired is a credential on the disk.

    The administrator's tokens live in files (there is no column for
    them in `tbladmins`), and a file that nobody removes accumulates.
    """
    admin_dir = ROOT / "storage" / "reset-tokens" / "admin"
    admin_dir.mkdir(parents=True, exist_ok=True)

    expired = admin_dir / "token-expirado-de-teste.json"
    expired.write_text(
        '{"username":"xkhm-test","expires":1}', encoding="utf-8"
    )

    valid = admin_dir / "token-valido-de-teste.json"
    valid.write_text(
        '{"username":"xkhm-test","expires":%d}' % (int(time.time()) + 3600),
        encoding="utf-8",
    )

    result = run_mailer(2525)

    assert result.returncode == 0, result.stderr
    assert not expired.exists(), "the expired token must be gone"
    assert valid.exists(), "the valid token must stay"

    valid.unlink(missing_ok=True)


def test_a_real_address_is_not_reserved():
    """The rule must not catch a real customer's address."""
    spec = importlib.util.spec_from_file_location("mailer", MAILER)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)

    assert module.is_reserved("xkhm-test@example.com") is True
    assert module.is_reserved("alguem@linuxkafe.com") is False
    assert module.is_reserved("alguem@gmail.com") is False
    assert module.is_reserved("sem-arroba") is False

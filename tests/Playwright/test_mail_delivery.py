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
def _the_host_mailer_must_not_run():
    """
    The host's mailer runs from the `crontab`, every minute.

    This test drives the very same queue, so the two would fight over
    it: the `cron` could deliver the token to the real Postfix between
    the application writing it and this test reading it.

    The `crontab` entry is therefore removed for the duration of the
    test, and restored afterwards — including when the test fails.
    """
    original = subprocess.run(
        ["crontab", "-l"], capture_output=True, text=True
    ).stdout

    kept = [
        line
        for line in original.splitlines()
        if "mail_pending_tokens" not in line
    ]

    subprocess.run(
        ["crontab", "-"],
        input="\n".join(kept) + ("\n" if kept else ""),
        text=True,
        capture_output=True,
    )

    # The queue starts empty: a leftover from another run would make
    # the counts wrong.
    for path in pending_files():
        path.unlink()
    for path in sorted(FAILED_DIR.glob("*.txt")) if FAILED_DIR.is_dir() else []:
        path.unlink()

    yield

    subprocess.run(
        ["crontab", "-"],
        input=original,
        text=True,
        capture_output=True,
    )

    for path in pending_files():
        path.unlink()


# ─── A fake SMTP server ──────────────────────────────────────────────────────


class FakeSmtp:
    """
    A minimal SMTP server that records the messages it receives.
    """

    def __init__(self) -> None:
        self.messages: list[str] = []
        self._server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self._server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self._server.bind(("127.0.0.1", 0))
        self._server.listen(5)
        self.port = self._server.getsockname()[1]
        self._running = True
        self._thread = threading.Thread(target=self._serve, daemon=True)
        self._thread.start()

    def _serve(self) -> None:
        while self._running:
            try:
                conn, _ = self._server.accept()
            except OSError:
                return

            threading.Thread(target=self._handle, args=(conn,), daemon=True).start()

    def _handle(self, conn: socket.socket) -> None:
        stream = conn.makefile("rwb")
        stream.write(b"220 fake ESMTP\r\n")
        stream.flush()

        while True:
            line = stream.readline()
            if not line:
                break

            text = line.decode("utf-8", "replace").strip()
            upper = text.upper()

            if upper.startswith("EHLO"):
                stream.write(b"250-fake\r\n250 SIZE 10240000\r\n")
            elif upper.startswith("HELO"):
                stream.write(b"250 fake\r\n")
            elif upper.startswith(("MAIL FROM", "RCPT TO")):
                stream.write(b"250 Ok\r\n")
            elif upper.startswith("DATA"):
                stream.write(b"354 go ahead\r\n")
                stream.flush()

                body: list[str] = []
                while True:
                    chunk = stream.readline()
                    if not chunk or chunk.strip() == b".":
                        break
                    body.append(chunk.decode("utf-8", "replace"))

                self.messages.append("".join(body))
                stream.write(b"250 Ok: queued\r\n")
            elif upper.startswith("QUIT"):
                stream.write(b"221 Bye\r\n")
                stream.flush()
                break
            else:
                stream.write(b"250 Ok\r\n")

            stream.flush()

        conn.close()

    def stop(self) -> None:
        self._running = False
        self._server.close()


@pytest.fixture
def fake_smtp():
    server = FakeSmtp()
    yield server
    server.stop()


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

    message = fake_smtp.messages[0]

    assert "Subject:" in message
    assert "To: " in message
    assert "Codigo:" in message
    assert RESET_ADMIN_USER in message

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


def test_a_real_address_is_not_reserved():
    """The rule must not catch a real customer's address."""
    spec = importlib.util.spec_from_file_location("mailer", MAILER)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)

    assert module.is_reserved("xkhm-test@example.com") is True
    assert module.is_reserved("alguem@linuxkafe.com") is False
    assert module.is_reserved("alguem@gmail.com") is False
    assert module.is_reserved("sem-arroba") is False

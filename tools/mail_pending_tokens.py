#!/usr/bin/env python3
"""
Deliver the pending credential-recovery tokens by e-mail.

**Why this runs on the host and not in the application:** the
application runs in a container, and the host's firewall does not let a
container reach the host — port 25 included (the connection times out).
The mail server *is* on the host, on `127.0.0.1:25`, and it accepts a
submission from the loopback without authentication.

So the division of labour is:

- the **application** writes the token to a file (the queue);
- this **mailer** reads the queue and sends it, then removes the file.

Exactly one consumer: a token that was already delivered by the
application itself never reaches this queue.

    make db-mail-send          # deliver whatever is pending
    make db-mail-send --dry    # show what would be sent

The crontab runs it every minute:

    * * * * * python3 ~/dev/xkhm/tools/mail_pending_tokens.py
"""

from __future__ import annotations

import argparse
import pathlib
import smtplib
import sys
from email.message import EmailMessage

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

from load_env import load_env  # noqa: E402

ROOT = pathlib.Path(__file__).resolve().parent.parent
TOKEN_DIR = pathlib.Path(
    __import__("os").getenv(
        "XKHM_RESET_TOKEN_DIR", str(ROOT / "storage" / "reset-tokens")
    )
)
PENDING_DIR = TOKEN_DIR / "pending"
FAILED_DIR = TOKEN_DIR / "failed"

#: The domains reserved for testing and documentation (RFC 2606, RFC 6761).
#:
#: They are guaranteed **not** to exist and **not** to accept mail. A
#: message sent to one is always a mistake: it goes out through the
#: relay, is refused, and comes back as a bounce — clutter in a real
#: mailbox, and traffic through someone else's relay account.
#:
#: The test accounts use addresses in these domains, so the mailer
#: refuses them by default. A real customer's address is never in here.
RESERVED_DOMAINS = {
    "example.com",
    "example.net",
    "example.org",
    "localhost",
    "invalid",
    "test",
    "local",
}

#: The special-use names of RFC 6762 (`.local`, `.home.arpa`, …). They
#: are matched on the **last** label, because they are used as the
#: top-level domain: `machine.local`, `machine.home.arpa`.
RESERVED_SUFFIXES = (
    ".local",
    ".localhost",
    ".home.arpa",
    ".internal",
    ".intranet",
    ".private",
    ".corp",
    ".home",
    ".lan",
)

#: The same names, used as a top-level domain on their own.
RESERVED_TLDS = {
    "local",
    "localhost",
    "home",
    "internal",
    "intranet",
    "private",
    "corp",
    "lan",
    "test",
    "invalid",
    "example",
}


def is_reserved(address: str) -> bool:
    """
    Is this one of the reserved, non-existent domains?

    `xkhm-reset@localhost` and `xkhm-test@example.com` are both
    reserved: they are the development accounts, and there is nobody
    behind them.
    """
    _, _, domain = address.rpartition("@")

    if not domain:
        return False

    domain = domain.strip().lower().rstrip(".")

    # `foo.bar.example.com` is reserved too: the rule applies to the
    # last two labels.
    labels = domain.split(".")

    if len(labels) >= 2 and ".".join(labels[-2:]) in RESERVED_DOMAINS:
        return True

    if domain in RESERVED_DOMAINS:
        return True

    # A name with no dot is a top-level domain on its own.
    if len(labels) == 1:
        return domain in RESERVED_TLDS

    return any(domain.endswith(suffix) for suffix in RESERVED_SUFFIXES)


def parse_pending(path: pathlib.Path) -> dict[str, str]:
    """
    Read a pending token file.

    The format is the one the application writes:

        purpose: cliente
        recipient: someone@example.com
        account: someone@example.com
        token: <the token>
        created: 2026-10-21T15:00:00+00:00
    """
    fields: dict[str, str] = {}

    for line in path.read_text(encoding="utf-8").splitlines():
        if ":" not in line:
            continue
        key, _, value = line.partition(":")
        fields[key.strip()] = value.strip()

    return fields


def build_message(fields: dict[str, str]) -> EmailMessage:
    """
    Build the message that carries the token.
    """
    purpose = fields.get("purpose", "cliente")
    account = fields.get("account", "")
    token = fields.get("token", "")

    message = EmailMessage()
    message["Subject"] = (
        "LINUXKAFE — redefinicao da senha de administrador"
        if purpose == "admin"
        else "LINUXKAFE — redefinicao da senha"
    )
    message.set_content(
        "Ola,\n\n"
        "Foi pedido um pedido de redefinicao de senha"
        + (f' para a conta "{account}"' if account else "")
        + ".\n\n"
        f"Codigo: {token}\n\n"
        "O pedido e valido durante uma hora e so pode ser usado uma vez.\n"
        "Se nao foi voce, ignore esta mensagem.\n"
    )

    return message


def send(message: EmailMessage, host: str, port: int, sender: str) -> None:
    """
    Hand the message to the SMTP server.

    No authentication: a local Postfix accepts a submission from the
    loopback, and a relay that *does* want credentials would refuse an
    empty one.
    """
    with smtplib.SMTP(host, port, timeout=20) as smtp:
        smtp.send_message(message, from_addr=sender)


def main() -> int:
    parser = argparse.ArgumentParser(description="Deliver the pending tokens.")
    parser.add_argument("--dry", action="store_true", help="only show what would be sent")
    args = parser.parse_args()

    load_env()

    import os

    host = os.getenv("XKHM_MAILER_SMTP_HOST", "127.0.0.1")
    port = int(os.getenv("XKHM_MAILER_SMTP_PORT", "25"))
    sender = os.getenv("XKHM_MAILER_SMTP_FROM", "safedata@linuxkafe.com")

    # The escape hatch: the reserved domains are refused by default,
    # because sending to them is always a mistake. The test suite needs
    # to deliver to them, and so does anybody who wants to try the flow
    # against a local address.
    allow_reserved = os.getenv("XKHM_MAILER_ALLOW_RESERVED", "") not in (
        "",
        "0",
        "false",
    )

    if not PENDING_DIR.is_dir():
        return 0

    sent = 0
    failed = 0

    for path in sorted(PENDING_DIR.glob("*.txt")):
        try:
            fields = parse_pending(path)
        except OSError as exc:
            print(f"  [ERRO] {path.name}: {exc}")
            failed += 1
            continue

        recipient = fields.get("recipient", "")
        token = fields.get("token", "")

        if not recipient or not token:
            print(f"  [ERRO] {path.name}: ficheiro incompleto — movido para failed/")
            move_to_failed(path, "ficheiro incompleto")
            failed += 1
            continue

        if is_reserved(recipient) and not allow_reserved:
            # A reserved domain does not exist and does not accept mail.
            # Sending there is always a mistake.
            move_to_failed(
                path,
                "dominio de teste reservado — nao ha ninguem para receber",
            )
            print(f"  [skip]  {recipient} (dominio reservado)")
            continue

        if args.dry:
            print(f"  [dry]  {recipient} <- {token[:16]}…")
            continue

        message = build_message(fields)
        message["From"] = sender
        message["To"] = recipient

        try:
            send(message, host, port, sender)
        except Exception as exc:
            print(f"  [ERRO] {recipient}: {exc}")
            move_to_failed(path, str(exc))
            failed += 1
            continue

        # Delivered: it is no longer pending.
        path.unlink(missing_ok=True)
        sent += 1
        print(f"  [OK]    {recipient}")

    if not args.dry and (sent or failed):
        print(f"  {sent} entregue(s), {failed} com falha.")

    return 0


def move_to_failed(path: pathlib.Path, reason: str) -> None:
    """
    Move a token that could not be delivered.

    Nothing is deleted: a token that bounces is still a token the
    administrator may need to hand over. It is kept, with the reason.
    """
    FAILED_DIR.mkdir(parents=True, exist_ok=True)

    target = FAILED_DIR / path.name
    target.write_text(
        path.read_text(encoding="utf-8") + f"\nfailed: {reason}\n",
        encoding="utf-8",
    )
    path.unlink(missing_ok=True)


if __name__ == "__main__":
    sys.exit(main())

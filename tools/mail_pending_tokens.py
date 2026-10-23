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
import json
import os
import pathlib
import smtplib
import sys
import time
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
ADMIN_DIR = TOKEN_DIR / "admin"

#: The root of the storage. The attachment paths in the queue are
#: **relative** to it: the queue is written by the application (inside
#: the container) and read by the mailer (on the host), and the two do
#: not share the same absolute paths.
STORAGE_ROOT = TOKEN_DIR.parent

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


#: The addresses that may receive the **payment notices**.
#:
#: The installation is not validated yet: the payment notice says, on
#: its face, that it is not a fiscal document. Sending it to a real
#: client would put that document in the inbox of somebody who paid.
#:
#: The same list is read by the application (`src/Service/MailPolicy.php`),
#: so the notice is never even addressed to a client. This is the
#: **last gate**: a misconfiguration upstream cannot reach a client
#: either.
NOTICE_RECIPIENTS_ENV = "XKHM_MAILER_NOTICE_RECIPIENTS"

#: The operator's address, until the operator says otherwise.
DEFAULT_NOTICE_RECIPIENTS = "mail@linuxkafe.com"


def notice_recipients() -> set[str]:
    """
    The addresses that may receive the payment notices.
    """
    raw = os.getenv(NOTICE_RECIPIENTS_ENV, "").strip()

    if not raw:
        raw = DEFAULT_NOTICE_RECIPIENTS

    addresses = {
        address.strip().lower()
        for address in raw.split(",")
        if address.strip()
    }

    return addresses or {DEFAULT_NOTICE_RECIPIENTS}


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


def purge_expired_admin_tokens() -> int:
    """
    Remove the administrator's tokens that have expired.

    The tokens of the administrator live in files, because `tbladmins`
    has no column for them. A file that nobody removes is a file that
    accumulates — and a token that expired an hour ago is still a
    credential sitting on the disk.

    This runs from the same place as the delivery: every minute.
    """
    if not ADMIN_DIR.is_dir():
        return 0

    now = int(time.time())
    removed = 0

    for path in ADMIN_DIR.glob("*.json"):
        try:
            record = json.loads(path.read_text(encoding="utf-8"))
            expires = int(record.get("expires", 0))
        except (OSError, ValueError):
            # A file that cannot be read is not a token we can trust:
            # it goes too.
            record = None
            expires = 0

        if record is None or expires < now:
            path.unlink(missing_ok=True)
            removed += 1

    return removed


def parse_pending(path: pathlib.Path) -> dict[str, str]:
    """
    Read a pending token file.

    The format is the one the application writes:

        purpose: cliente
        recipient: someone@example.com
        account: someone@example.com
        token: <the token>
        created: 2026-10-21T15:00:00+00:00

    Two lines are optional:

        subject: <the subject of the message>
        attachment: <the path of a file to attach>

    The payment notice uses them: the application generates the PDF
    (it has the application) and the mailer only attaches it (it has
    the file).
    """
    fields: dict[str, str] = {}

    for line in path.read_text(encoding="utf-8").splitlines():
        if ":" not in line:
            continue
        key, _, value = line.partition(":")
        fields[key.strip()] = value.strip()

    return fields


def reset_link(public_url: str, purpose: str, token: str) -> str:
    """
    The link that takes the person to the form.

    The token always travels in the **fragment** (`#reset=…`), never in
    the query string. The browser does not send the fragment to the
    server, so the token does not end up in the access log, nor in a
    `Referer` header. Both the application and the administrator's page
    read it from there and take it out of the address bar.

    What travels in the query string is only what is not a secret:
    `action=reset` says which page to render.
    """
    base = public_url.rstrip("/")

    if purpose == "admin":
        return f"{base}/admin/login.php?action=reset#reset={token}"

    return f"{base}/app/#reset={token}"


def build_message(fields: dict[str, str], public_url: str) -> EmailMessage:
    """
    Build the message that carries the token.

    The **link** is the primary action — the person clicks and lands on
    the form. The **code** comes as the alternative, for the person who
    reads the e-mail on another device and cannot click it. The code
    never disappears: the two paths are complementary.

    A **payment notice** is different: it carries a document, not a
    code, and the document comes as an attachment.
    """
    purpose = fields.get("purpose", "cliente")
    account = fields.get("account", "")
    token = fields.get("token", "")

    if purpose == "aviso":
        return build_notice_message(fields)

    link = reset_link(public_url, purpose, token)

    is_admin = purpose == "admin"
    subject = (
        "LINUXKAFE — redefinir a senha de administrador"
        if is_admin
        else "LINUXKAFE — redefinir a senha"
    )

    greeting = "Ola,"

    if is_admin:
        intro = (
            "Foi pedido um pedido de redefinicao da senha de administrador"
            + (f' para a conta "{account}"' if account else "")
            + "."
        )
    else:
        intro = (
            "Foi pedido um pedido de redefinicao de senha"
            + (f' para a conta "{account}"' if account else "")
            + "."
        )

    text = (
        f"{greeting}\n\n"
        f"{intro}\n\n"
        "Para definir a nova senha, abra este endereco:\n\n"
        f"    {link}\n\n"
        "O pedido e valido durante uma hora e so pode ser usado uma vez.\n\n"
        "Se o endereco nao funcionar, use este codigo na pagina de "
        "redefinicao:\n\n"
        f"Codigo: {token}\n\n"
        "Se nao foi voce, ignore esta mensagem: nada acontece.\n"
    )

    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{subject}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e4e4e7;">
    <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;">Redefinir a senha</h1>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">{intro}</p>
    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;">
      Clique no botao para definir a nova senha. O pedido e valido durante
      uma hora e so pode ser usado uma vez.
    </p>
    <p style="margin:0 0 24px;">
      <a href="{link}"
         style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;font-size:15px;font-weight:600;">
        Definir a nova senha
      </a>
    </p>
    <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#71717a;">
      Se o botao nao funcionar, copie este endereco para o navegador:
    </p>
    <p style="margin:0 0 24px;font-size:13px;line-height:1.6;word-break:break-all;">
      <a href="{link}" style="color:#2563eb;">{link}</a>
    </p>
    <hr style="border:none;border-top:1px solid #e4e4e7;margin:24px 0;">
    <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#71717a;">
      Prefere copiar e colar? Use este codigo na pagina de redefinicao:
    </p>
    <p style="margin:0 0 24px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:14px;word-break:break-all;">
      {token}
    </p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#71717a;">
      Se nao foi voce, ignore esta mensagem: nada acontece.
    </p>
  </div>
</body>
</html>
"""

    message = EmailMessage()
    message["Subject"] = subject
    message.set_content(text)
    message.add_alternative(html, subtype="html")

    return message


def build_notice_message(fields: dict[str, str]) -> EmailMessage:
    """
    The payment notice: a document, and where to find it.

    While the installation is not validated, the notice goes to the
    **operator** — and the body says whose it is, so that a pile of
    them is still readable.
    """
    account = fields.get("account", "")
    token = fields.get("token", "")
    subject = fields.get("subject") or "Aviso de pagamento"
    attachment = fields.get("attachment", "")
    client = fields.get("client", "").strip()

    body = (
        "Olá,\n\n"
        "Segue em anexo o aviso de pagamento referente à fatura " + token + ".\n\n"
        "Neste documento pode consultar o montante em dívida, a data limite e as "
        "opções de pagamento disponíveis.\n\n"
        "Nota: Este documento é um aviso de pagamento e não substitui a fatura. "
        "A fatura certificada será emitida separadamente.\n\n"
        "Com os melhores cumprimentos,\n"
        + (fields.get("signature", "") or "LINUXKAFE")
        + "\n"
    )

    if client:
        body += (
            "\n---\n"
            "Este aviso foi gerado para o cliente " + client + ".\n"
            "A instalação ainda não está validada, e por isso o aviso não "
            "foi enviado ao cliente: é para validação.\n"
        )

    message = EmailMessage()
    message["Subject"] = subject
    message.set_content(body)

    # O corpo em HTML, com a formatacao do tema.
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{subject}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e4e4e7;">
    <div style="background:#ffffff;padding:16px;border-radius:8px 8px 0 0;margin:-32px -32px 24px;border-bottom:1px solid #e4e4e7;">
      <img src="https://www.linuxkafe.com/wp-content/uploads/2026/01/linuxkafe.svg" alt="LINUXKAFE" height="40" style="display:block;height:40px;width:auto;">
    </div>
    <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#212121;">{subject}</h1>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#555555;">{body.replace(chr(10), '<br>')}</p>
    <p style="margin:24px 0 0;font-size:12px;line-height:1.6;color:#71717a;">
      Este e-mail foi gerado automaticamente pelo sistema xkhm.
    </p>
  </div>
</body>
</html>
"""
    message.add_alternative(html, subtype="html")

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


def resolve_attachment(attachment: str) -> pathlib.Path:
    """
    The attachment, from the path the queue carries.

    The path is relative to the storage root, so that the queue can be
    written inside the container and read on the host.
    """
    candidate = pathlib.Path(attachment)

    if candidate.is_absolute():
        return candidate

    return STORAGE_ROOT / candidate


def send_with_attachment(
    message: EmailMessage,
    host: str,
    port: int,
    sender: str,
    attachment: str,
) -> None:
    """
    Send a message with a file attached.

    The attachment goes on **before** the message is sent: adding it
    afterwards and sending again would deliver the message twice.
    """
    path = resolve_attachment(attachment)

    if path.is_file():
        message.add_attachment(
            path.read_bytes(),
            maintype="application",
            subtype="pdf",
            filename=path.name,
        )

    with smtplib.SMTP(host, port, timeout=20) as smtp:
        smtp.send_message(message, from_addr=sender)


def main() -> int:
    parser = argparse.ArgumentParser(description="Deliver the pending tokens.")
    parser.add_argument("--dry", action="store_true", help="only show what would be sent")
    args = parser.parse_args()

    load_env()

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

    # The public address of the site. The links in the e-mail have to
    # point at the address the customer actually uses, not at the
    # container.
    public_url = os.getenv("XKHM_PUBLIC_URL", "http://localhost:8083")

    # The tokens that expired are cleaned up, whether or not there is
    # anything to deliver.
    purge_expired_admin_tokens()

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
        purpose = fields.get("purpose", "").strip().lower()

        if not recipient or not token:
            print(f"  [ERRO] {path.name}: ficheiro incompleto — movido para failed/")
            move_to_failed(path, "ficheiro incompleto")
            failed += 1
            continue

        # The payment notices are restricted: until the installation is
        # validated, they only go to the operator.
        if purpose == "aviso" and recipient.lower() not in notice_recipients():
            move_to_failed(
                path,
                "aviso de pagamento para um cliente — a instalacao ainda "
                "nao esta validada (destinatario permitido: "
                + ", ".join(sorted(notice_recipients()))
                + ")",
            )
            print(f"  [skip]  {recipient} (aviso: instalacao nao validada)")
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

        message = build_message(fields, public_url)
        message["From"] = sender
        message["To"] = recipient

        attachment = fields.get("attachment", "").strip()

        try:
            if attachment:
                send_with_attachment(message, host, port, sender, attachment)
            else:
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

#!/usr/bin/env python3
"""
Create the development test accounts — and their sample data — in the local
database.

The E2E suite needs a client and an admin whose passwords it knows, and
data to assert on. The production dump has neither: the real accounts'
passwords are the operator's, not ours to change, and the real data belongs
to the real clients.

Everything created here lives only in the **local** copy of the database
and is clearly marked as development data.

    make db-test-accounts
"""

from __future__ import annotations

import datetime
import os
import pathlib
import subprocess
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from load_env import load_env  # noqa: E402

load_env()

DB_CONTAINER = "xkhm-mysql-1"
APP_CONTAINER = "xkhm-app-1"
DB_NAME = os.getenv("XKHM_DB_NAME", "linuxkonect")
DB_USER = os.getenv("XKHM_DB_USER", "linuxkonect")
DB_PASS = os.getenv("XKHM_DB_PASSWORD", "")

CLIENT_EMAIL = os.getenv("XKHM_TEST_CLIENT", "xkhm-test@example.com")
CLIENT_PASS = os.getenv("XKHM_TEST_CLIENT_PASS", "xkhm-test-2026")

#: A second client, used by the IDOR test: it must see nothing of the
#: first client's data.
OTHER_EMAIL = os.getenv("XKHM_TEST_CLIENT2", "xkhm-test2@example.com")
OTHER_PASS = os.getenv("XKHM_TEST_CLIENT2_PASS", "xkhm-test-2026")

ADMIN_USER = os.getenv("XKHM_TEST_ADMIN", "xkhm-test")
ADMIN_PASS = os.getenv("XKHM_TEST_ADMIN_PASS", "xkhm-test-2026")

#: A third client and a second admin, reserved for the recovery tests: those
#: **change** the password, and the shared accounts must stay untouched.
RESET_CLIENT_EMAIL = os.getenv("XKHM_RESET_TEST_CLIENT", "xkhm-reset@example.com")
RESET_CLIENT_PASS = os.getenv("XKHM_RESET_TEST_CLIENT_PASS", "xkhm-reset-2026")

RESET_ADMIN_USER = os.getenv("XKHM_RESET_TEST_ADMIN", "xkhm-reset-admin")
RESET_ADMIN_PASS = os.getenv("XKHM_RESET_TEST_ADMIN_PASS", "xkhm-reset-2026")


# ─── Database helpers ────────────────────────────────────────────────────────


def mysql(sql: str) -> str:
    result = subprocess.run(
        [
            "docker", "exec", "-i", DB_CONTAINER,
            "mysql", f"-u{DB_USER}", f"-p{DB_PASS}", "-N", "-B", DB_NAME,
            "-e", sql,
        ],
        capture_output=True,
        text=True,
        timeout=120,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "mysql failed")

    return result.stdout.strip()


def sql_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def build_insert(table: str, values: dict[str, object]) -> str:
    """An INSERT whose columns and values come from the same dict."""
    columns = list(values.keys())
    rendered = ", ".join(
        'NULL' if value is None
        else str(value) if isinstance(value, (int, float))
        else "'" + sql_escape(str(value)) + "'"
        for value in values.values()
    )

    # `order` is a reserved word in MySQL.
    quoted = ", ".join(f"`{column}`" for column in columns)

    return f"INSERT INTO {table} ({quoted}) VALUES ({rendered});"


def schema_defaults(table: str) -> dict[str, object]:
    """The default value of every column the real schema requires.

    WHMCS 5.3.9 declares almost every column as NOT NULL **without a
    default**, so the values are derived from the live schema rather than
    written by hand.
    """
    rows = mysql(
        "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS "
        f"WHERE TABLE_SCHEMA='{DB_NAME}' AND TABLE_NAME='{table}' "
        "AND IS_NULLABLE='NO' AND COLUMN_DEFAULT IS NULL "
        "AND EXTRA NOT LIKE '%auto_increment%' ORDER BY ORDINAL_POSITION;"
    ).splitlines()

    defaults: dict[str, object] = {}
    for row in rows:
        if "\t" not in row:
            continue
        name, data_type = row.split("\t", 1)
        if data_type in ('tinyint', 'smallint', 'mediumint', 'int', 'bigint'):
            defaults[name] = 0
        elif data_type in ('decimal', 'float', 'double'):
            defaults[name] = 0.0
        elif data_type == 'date':
            defaults[name] = datetime.date.today().isoformat()
        elif data_type in ('datetime', 'timestamp'):
            defaults[name] = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        else:
            defaults[name] = ''

    return defaults


def bcrypt_hash(password: str) -> str:
    """A bcrypt hash of the password, produced by the same PHP the app uses."""
    result = subprocess.run(
        [
            "docker", "exec", APP_CONTAINER, "php", "-r",
            'echo password_hash($argv[1], PASSWORD_BCRYPT);',
            password,
        ],
        capture_output=True,
        text=True,
        timeout=60,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "php failed")

    return result.stdout.strip()


def upsert_client(email: str, password: str, firstname: str) -> int:
    """Create or refresh a development client. Returns its id."""
    stored = bcrypt_hash(password)

    existing = mysql(f"SELECT id FROM tblclients WHERE email = '{sql_escape(email)}';")
    if existing:
        mysql(
            f"UPDATE tblclients SET password = '{sql_escape(stored)}', "
            f"status = 'Active' WHERE email = '{sql_escape(email)}';"
        )
        return int(existing)

    values = schema_defaults('tblclients')
    values.update({
        'firstname': firstname,
        'lastname': 'Teste',
        'email': email,
        'password': stored,
        'currency': 1,
        'credit': '0.00',
        'datecreated': datetime.date.today().isoformat(),
        'groupid': 1,
        'status': 'Active',
    })
    # The INSERT and the id lookup must share one connection: LAST_INSERT_ID()
    # is per-connection.
    return int(mysql(build_insert('tblclients', values) + " SELECT LAST_INSERT_ID();"))


def upsert_admin(username: str, password: str) -> int:
    stored = bcrypt_hash(password)

    existing = mysql(f"SELECT id FROM tbladmins WHERE username = '{sql_escape(username)}';")
    if existing:
        mysql(
            f"UPDATE tbladmins SET password = '{sql_escape(stored)}', "
            f"disabled = 0 WHERE username = '{sql_escape(username)}';"
        )
        return int(existing)

    values = schema_defaults('tbladmins')
    values.update({
        'roleid': 1,
        'username': username,
        'password': stored,
        'firstname': 'Xkhm',
        'lastname': 'Teste',
        'email': 'xkhm-test@localhost',
        'language': 'portuguese',
        'disabled': 0,
    })
    return int(mysql(build_insert('tbladmins', values) + " SELECT LAST_INSERT_ID();"))


# ─── The sample data ─────────────────────────────────────────────────────────


def seed_client_services(client_id: int) -> None:
    """Give the development client services of **different types**.

    `tblhosting` holds every service — the web hosting, the backups, the
    servers. The product says which is which, and the group is the label
    the client sees.

    The development client gets one of each, so that the difference is
    visible — and so that the tests have something to check.
    """
    if mysql(f"SELECT COUNT(*) FROM tblhosting WHERE userid = {client_id};") != "0":
        return

    # The products that exist in the real catalogue, by group.
    packages = [
        # (packageid, the domain, the description of what it is)
        (21, 'xkhm-teste.example.com', 'Alojamento'),
        (24, '', 'Backup'),
    ]

    for package_id, domain, kind in packages:
        values = schema_defaults('tblhosting')
        values.update({
            'userid': client_id,
            'orderid': 0,
            'packageid': package_id,
            'server': 1 if kind == 'Alojamento' else 0,
            'regdate': datetime.date.today().isoformat(),
            'domain': domain,
            'paymentmethod': 'banktransfer',
            'firstpaymentamount': '0.00',
            'amount': '24.99' if kind == 'Alojamento' else '12.00',
            'billingcycle': 'Annually',
            'nextduedate': (
                datetime.date.today() + datetime.timedelta(days=365)
            ).isoformat(),
            'nextinvoicedate': (
                datetime.date.today() + datetime.timedelta(days=350)
            ).isoformat(),
            'domainstatus': 'Active',
            'username': 'xkhm' if kind == 'Alojamento' else '',
            'password': 'desenvolvimento',
            'notes': f'Serviço de desenvolvimento ({kind})',
            'subscriptionid': '',
            'promoid': 0,
            'suspendreason': '',
            'overideautosuspend': '',
            'dedicatedip': '',
            'assignedips': '',
            'ns1': 'ns1.linuxkafe.com' if kind == 'Alojamento' else '',
            'ns2': 'ns2.linuxkafe.com' if kind == 'Alojamento' else '',
            'diskusage': 1024 if kind == 'Alojamento' else 0,
            'disklimit': 10240 if kind == 'Alojamento' else 0,
            'bwusage': 512 if kind == 'Alojamento' else 0,
            'bwlimit': 10240 if kind == 'Alojamento' else 0,
            'lastupdate': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        })

        mysql(build_insert('tblhosting', values))


def seed_client_data(client_id: int) -> None:
    """Give the development client something to look at.

    The production data belongs to the production clients; the
    development account gets its own rows.
    """
    if mysql(f"SELECT COUNT(*) FROM tblinvoices WHERE userid = {client_id};") != "0":
        return

    today = datetime.date.today()

    # Two invoices, one paid and one not.
    invoice_ids = []
    for index, (status, total, days_ago) in enumerate(
        [('Unpaid', '149.90', 30), ('Paid', '24.99', 60)]
    ):
        created = today - datetime.timedelta(days=days_ago)
        due = created + datetime.timedelta(days=14)

        values = schema_defaults('tblinvoices')
        values.update({
            'userid': client_id,
            'invoicenum': str(9000 + index),
            'date': created.isoformat(),
            'duedate': due.isoformat(),
            'subtotal': total,
            'credit': '0.00',
            'tax': '0.00',
            'tax2': '0.00',
            'total': total,
            'taxrate': '0.00',
            'taxrate2': '0.00',
            'status': status,
            'paymentmethod': 'banktransfer',
            'notes': 'Fatura de desenvolvimento',
        })
        invoice_ids.append(int(mysql(
            build_insert('tblinvoices', values) + " SELECT LAST_INSERT_ID();"
        )))

        item = schema_defaults('tblinvoiceitems')
        item.update({
            'invoiceid': invoice_ids[-1],
            'userid': client_id,
            'type': 'hosting',
            'relid': 0,
            'description': 'Alojamento Web Partilhado - 1 ano',
            'amount': total,
            'taxed': 0,
            'paymentmethod': 'banktransfer',
            'notes': '',
        })
        mysql(build_insert('tblinvoiceitems', item))

    # One order.
    order = schema_defaults('tblorders')
    order.update({
        'ordernum': 9000,
        'userid': client_id,
        'contactid': 0,
        'date': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'amount': '149.90',
        'paymentmethod': 'banktransfer',
        'status': 'Completed',
        'notes': 'Pedido de desenvolvimento',
    })
    order_id = int(mysql(
        build_insert('tblorders', order) + " SELECT LAST_INSERT_ID();"
    ))

    # One domain.
    domain = schema_defaults('tbldomains')
    domain.update({
        'userid': client_id,
        'orderid': order_id,
        'type': 'Register',
        'registrationdate': today.isoformat(),
        'domain': 'exemplo-xkhm.com',
        'recurringamount': '149.90',
        'registrar': 'linuxkafe',
        'expirydate': (today + datetime.timedelta(days=365)).isoformat(),
        'nextduedate': (today + datetime.timedelta(days=365)).isoformat(),
        'nextinvoicedate': (today + datetime.timedelta(days=365)).isoformat(),
        'status': 'Active',
    })
    mysql(build_insert('tbldomains', domain))

    # One ticket, with a reply.
    ticket = schema_defaults('tbltickets')
    ticket.update({
        'tid': '9001',
        'did': 0,
        'userid': client_id,
        'contactid': 0,
        'name': 'Xkhm Teste',
        'email': CLIENT_EMAIL,
        'title': 'Duvida sobre a fatura',
        'message': 'Mensagem de desenvolvimento.',
        'status': 'Open',
        'urgency': 'Medium',
        'lastreply': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    })
    ticket_id = int(mysql(
        build_insert('tbltickets', ticket) + " SELECT LAST_INSERT_ID();"
    ))

    reply = schema_defaults('tblticketreplies')
    reply.update({
        'tid': ticket_id,
        'userid': client_id,
        'contactid': 0,
        'name': 'Suporte LINUXKAFE',
        'email': 'suporte@linuxkafe.com',
        'date': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'message': 'Resposta de desenvolvimento.',
        'admin': '1',
    })
    mysql(build_insert('tblticketreplies', reply))


def seed_global_content() -> None:
    """The knowledge base and the announcements are global.

    The production WHMCS has an empty knowledge base, so the application
    would show an empty screen; a couple of development entries make the
    feature demonstrable.
    """
    for index, (name, description) in enumerate(
        [
            ('Getting Started', 'Learn the basics'),
            ('Billing', 'Payment and invoice questions'),
        ],
        start=1,
    ):
        # Each piece is checked on its own, so a partial run is repaired.
        if mysql(f"SELECT COUNT(*) FROM tblknowledgebasecats WHERE name = '{sql_escape(name)}';") == "0":
            values = schema_defaults('tblknowledgebasecats')
            values.update({
                'name': name,
                'description': description,
                'hidden': '0',
                'catid': index,
                'language': '',
            })
            category_id = int(mysql(
                build_insert('tblknowledgebasecats', values)
                + " SELECT LAST_INSERT_ID();"
            ))
        else:
            category_id = int(mysql(
                f"SELECT id FROM tblknowledgebasecats WHERE name = '{sql_escape(name)}';"
            ))

        if mysql(
            f"SELECT COUNT(*) FROM tblknowledgebase WHERE title = "
            f"'{sql_escape(name + ': primeiros passos')}';"
        ) == "0":
            article = schema_defaults('tblknowledgebase')
            article.update({
                'title': f'{name}: primeiros passos',
                'article': f'Conteudo de desenvolvimento sobre {name.lower()}.',
                'private': '0',
                'order': index,
                'parentid': category_id,
                'language': '',
            })
            mysql(build_insert('tblknowledgebase', article))

    if mysql("SELECT COUNT(*) FROM tblannouncements;") == "0":
        values = schema_defaults('tblannouncements')
        values.update({
            'title': 'Bemvindo ao LINUXKAFE',
            'announcement': 'Obrigado por escolher os nossos servicos.',
            'published': 'on',
            'parentid': 0,
            'language': '',
        })
        mysql(build_insert('tblannouncements', values))


def main() -> int:
    print()
    print("Contas e dados de teste de desenvolvimento")
    print("=" * 62)

    try:
        client_id = upsert_client(CLIENT_EMAIL, CLIENT_PASS, 'Xkhm')
        other_id = upsert_client(OTHER_EMAIL, OTHER_PASS, 'Xkhm')
        admin_id = upsert_admin(ADMIN_USER, ADMIN_PASS)
        reset_client_id = upsert_client(
            RESET_CLIENT_EMAIL, RESET_CLIENT_PASS, 'Xkhm'
        )

        reset_admin_id = upsert_admin(RESET_ADMIN_USER, RESET_ADMIN_PASS)
    except RuntimeError as exc:
        print(f"  [FALHA] {exc}")
        return 1

    print(f"  [OK]    cliente  {CLIENT_EMAIL} (id {client_id})")
    print(f"  [OK]    cliente  {OTHER_EMAIL} (id {other_id})")
    print(f"  [OK]    admin    {ADMIN_USER} (id {admin_id})")
    print(f"  [OK]    cliente  {RESET_CLIENT_EMAIL} (id {reset_client_id}) — só para a recuperação")
    print(f"  [OK]    admin    {RESET_ADMIN_USER} (id {reset_admin_id}) — só para a recuperação")

    try:
        seed_client_data(client_id)
        seed_client_services(client_id)
        print("  [OK]    dados do cliente (faturas, pedido, dominio, ticket)")
        seed_global_content()
        print("  [OK]    base de conhecimento e anuncios")
    except RuntimeError as exc:
        print(f"  [FALHA] {exc}")
        return 1

    print()
    print("  Credenciais de teste:")
    print(f"    cliente  {CLIENT_EMAIL} / {CLIENT_PASS}")
    print(f"    cliente  {OTHER_EMAIL} / {OTHER_PASS}")
    print(f"    admin    {ADMIN_USER} / {ADMIN_PASS}")
    print(f"    cliente  {RESET_CLIENT_EMAIL} / {RESET_CLIENT_PASS}  (recuperação)")
    print(f"    admin    {RESET_ADMIN_USER} / {RESET_ADMIN_PASS}  (recuperação)")
    print()
    print("  NOTA: o admin real 'linuxkafe' NAO foi tocado — a senha")
    print("        continua a ser a de producao.")
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

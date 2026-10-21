#!/usr/bin/env python3
"""
Import a production database dump into the local MySQL, then verify it.

    make db-import FILE=docker/dumps/linuxkonect-20260721.sql

The dump carries its own `CREATE TABLE` statements, so the loader needs no
knowledge of the WHMCS schema — and, crucially, it *invents* nothing. The
development fixture (`docker/init.sql`) stays a fixture.

The steps are:

  1. back up the current local database
  2. drop and recreate the target schema
  3. apply the dump
  4. report: tables, rows, and any divergence from what the repositories
     expect

Step 4 is the important one: it is the difference between "the import ran" and
"the import produced a database the application can actually use".
"""

from __future__ import annotations

import argparse
import gzip
import lzma
import bz2
import os
import pathlib
import shutil
import subprocess
import sys
import time

ROOT = pathlib.Path(__file__).resolve().parent.parent
DUMP_DIR = ROOT / "docker" / "dumps"
BACKUP_DIR = ROOT / "docker" / "backups"

CONTAINER = "xkhm-mysql-1"
DB_NAME = os.getenv("XKHM_DB_NAME", "linuxkonect")
DB_USER = os.getenv("XKHM_DB_USER", "linuxkonect")
DB_PASS = os.getenv("XKHM_DB_PASS", "qm9W8amh5XV9A4Dr")

#: The tables the application actually reads. Anything else in the dump is
#: kept (it is production data) but is not used by xkhm yet.
EXPECTED_TABLES = [
    "tbladmins",
    "tblaffiliates",
    "tblaffiliatesclients",
    "tblaffiliatespayouts",
    "tblannouncements",
    "tblclients",
    "tbldomainpricing",
    "tbldomains",
    "tblinvoiceitems",
    "tblinvoices",
    "tblknowledgebase",
    "tblknowledgebasecats",
    "tblorders",
    "tblticketdepartments",
    "tblticketreplies",
    "tbltickets",
]

#: Tables the live WHMCS has that xkhm does not read yet. They are reported
#: as "preserved, unused" rather than as errors.
KNOWN_WHMCS_TABLES = {
    "tblaccounts", "tblhosting", "tblproducts", "tblproductgroups",
    "tblproductconfigoptions", "tblproductconfiglinks", "tblservers",
    "tblhostingaddons", "tblhostingconfigoptions", "tblcurrencies",
    "tblcustomfields", "tblcustomfieldsvalues", "tblpaymentgateways",
    "tblemailtemplates", "tblcontacts", "tbllicensing", "tblmediacp_licenses",
    "tbldomainreminders", "tblticketfeedback", "tblticketstatuses",
    "tblquotes", "tblpromotions", "tblregistrars", "tbltodolist",
    "tblticketattachments", "tblaffiliatespending", "tblaffiliatesaccounts",
    "tblclientsaddons", "tblclientscredits", "tblclientscontacts",
    "tblclientsdomaincontacts", "tblclientshosting", "tblclientsnotes",
    "tblconfiguration", "tblactivitylog", "tblnotificationqueue",
    "tblnotificationrules", "tblnotificationtemplates", "tbloauth",
    "tbloauthclients", "tbloauthscopes", "tbloauthauthcodes",
    "tblproductsconfigure", "tblproration", "tbltransfer",
    "tblupgrades", "tblwebhosting", "tblssl", "tblpromotions",
    "tblemailhistory", "tblfailedlogin", "tbluserlog", "tblinvoiceitems",
}


def run(command: list[str], *, stdin: int | None = None, capture: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(
        command,
        input=stdin,
        capture_output=capture,
        text=True,
        timeout=3600,
    )


def mysql(sql: str) -> str:
    """Run SQL inside the local MySQL container, with the target schema selected."""
    result = run(
        [
            "docker", "exec", "-i", CONTAINER,
            "mysql", f"-u{DB_USER}", f"-p{DB_PASS}", "-N", "-B", DB_NAME,
            "-e", sql,
        ]
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "mysql falhou")
    return result.stdout.strip()


def mysql_server(sql: str) -> str:
    """Run SQL with no schema selected.

    Used for the statements that create or drop the schema itself — it does
    not exist yet at the moment it is created.
    """
    result = run(
        [
            "docker", "exec", "-i", CONTAINER,
            "mysql", f"-u{DB_USER}", f"-p{DB_PASS}", "-N", "-B",
            "-e", sql,
        ]
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or "mysql falhou")
    return result.stdout.strip()


def open_dump(path: pathlib.Path):
    """Open a dump, transparently handling gzip / bzip2 / xz."""
    if path.suffix == ".gz":
        return gzip.open(path, "rt", encoding="utf-8", errors="replace")
    if path.suffix == ".bz2":
        return bz2.open(path, "rt", encoding="utf-8", errors="replace")
    if path.suffix == ".xz":
        return lzma.open(path, "rt", encoding="utf-8", errors="replace")
    return path.open("r", encoding="utf-8", errors="replace")


def backup_current(label: str) -> pathlib.Path | None:
    """Dump the current local database so the import is reversible."""
    if not shutil.which("mysqldump"):
        print("  [AVISO] mysqldump não está instalado — sem cópia de segurança")
        return None

    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    target = BACKUP_DIR / f"{DB_NAME}-{label}.sql"

    result = run(
        [
            "docker", "exec", CONTAINER,
            "mysqldump", f"-u{DB_USER}", f"-p{DB_PASS}",
            "--single-transaction", "--default-character-set=utf8mb4",
            "--no-tablespaces",   # the app user has no PROCESS privilege
            DB_NAME,
        ]
    )
    if result.returncode != 0:
        print(f"  [AVISO] a cópia de segurança falhou: {result.stderr.strip()[:120]}")
        return None

    target.write_text(result.stdout, encoding="utf-8")
    size = target.stat().st_size
    readable = f"{size / (1024 * 1024):.1f} MB" if size > 1024 * 1024 else f"{size / 1024:.0f} KB"
    print(f"  [OK]    cópia de segurança: {target} ({readable})")
    return target


def import_dump(path: pathlib.Path) -> None:
    """Apply the dump. It carries its own CREATE TABLE statements."""
    print(f"  A aplicar {path.name} …", flush=True)

    # The target schema is ours; the dump is applied into it.
    mysql_server(f"DROP DATABASE IF EXISTS `{DB_NAME}`;")
    mysql_server(
        f"CREATE DATABASE `{DB_NAME}` "
        "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    )

    # The dump is copied into the container first: a production dump can be
    # hundreds of megabytes, which does not belong on a command line.
    with open_dump(path) as handle:
        payload = handle.read()

    container_path = "/tmp/xkhm-import.sql"
    copy = subprocess.run(
        ["docker", "exec", "-i", CONTAINER, "sh", "-c", f"cat > {container_path}"],
        input=payload.encode("utf-8", errors="replace"),
        capture_output=True,
        timeout=3600,
    )
    if copy.returncode != 0:
        raise RuntimeError(copy.stderr.decode("utf-8", "replace")[:400])

    apply = subprocess.run(
        [
            "docker", "exec", CONTAINER, "sh", "-c",
            f"mysql -u{DB_USER} -p{DB_PASS} {DB_NAME} < {container_path}",
        ],
        capture_output=True,
        timeout=3600,
    )
    if apply.returncode != 0:
        raise RuntimeError(apply.stderr.decode("utf-8", "replace")[:800])

    run(["docker", "exec", CONTAINER, "rm", "-f", container_path])


def report() -> None:
    """Report what the imported database actually contains."""
    tables = [
        line.split("\t")[0]
        for line in mysql(
            "SELECT TABLE_NAME FROM information_schema.TABLES "
            "WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME;"
        ).splitlines()
        if line
    ]

    print()
    print("  Tabelas importadas: " + str(len(tables)))

    missing = [table for table in EXPECTED_TABLES if table not in tables]
    if missing:
        print()
        print("  [FALHA] Faltam tabelas que a aplicação usa:")
        for table in missing:
            print(f"          - {table}")
        print("          A aplicação nestas condições vai falhar ao ler esses dados.")
    else:
        print(f"  [OK]    As {len(EXPECTED_TABLES)} tabelas usadas pela aplicação existem.")

    unused = [table for table in tables if table in KNOWN_WHMCS_TABLES]
    if unused:
        print(f"  [INFO]  {len(unused)} tabelas do WHMCS preservadas mas ainda não usadas.")

    print()
    print("  Linhas por tabela (as que a aplicação lê):")
    for table in EXPECTED_TABLES:
        if table not in tables:
            continue
        try:
            count = mysql(f"SELECT COUNT(*) FROM `{table}`;")
        except RuntimeError as exc:
            print(f"    {table:<26} erro: {str(exc)[:60]}")
            continue
        print(f"    {table:<26} {count}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--file", required=True, help="the dump to import")
    parser.add_argument(
        "--no-backup", action="store_true", help="skip the backup step"
    )
    args = parser.parse_args()

    dump = pathlib.Path(args.file)
    if not dump.is_absolute():
        dump = ROOT / dump

    if not dump.exists():
        print(f"ERRO: o ficheiro {dump} não existe.")
        print("      Use `make db-check` para ver onde procurar o dump.")
        return 1

    print()
    print("Importação da base de dados")
    print("=" * 62)

    if not args.no_backup:
        print()
        print("1. Cópia de segurança")
        backup_current(f"pre-import-{time.strftime('%Y%m%d-%H%M%S')}")
    else:
        print()
        print("1. Cópia de segurança — ignorada (--no-backup)")

    print()
    print("2. Importação")
    try:
        import_dump(dump)
    except Exception as exc:  # noqa: BLE001 - the message is the output
        print(f"  [FALHA] {exc}")
        return 1
    print("  [OK]    dump aplicado")

    print()
    print("3. Verificação")
    report()

    print()
    print("=" * 62)
    print("Agora:")
    print("  make test        # o SchemaParityTest corre contra os dados reais")
    print("  docker compose up -d --force-recreate app")
    print("  make e2e         # a suíte E2E contra os dados importados")
    print()
    return 0


if __name__ == "__main__":
    sys.exit(main())

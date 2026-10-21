#!/usr/bin/env python3
"""
Set the password of a client account in the local database.

The production clients' passwords are the customers'; this tool is for the
**local** copy, and for the cases where a customer cannot recover their own
password.

    make db-reset-client EMAIL=cliente@exemplo.pt PASSWORD='a-nova-senha'

The password is written as bcrypt, in the same format WHMCS 6/7 uses, and
the reset token is cleared — the customer cannot use an old token to get
back in.
"""

from __future__ import annotations

import datetime
import getpass
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


def bcrypt_hash(password: str) -> str:
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


def sql_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def main() -> int:
    email = os.getenv("EMAIL", "")
    password = os.getenv("PASSWORD", "")

    print()
    print("Repor a senha de um cliente")
    print("=" * 62)

    if not email:
        print("  Indique o email do cliente:")
        email = input("  email: ").strip()

    if not password:
        print("  Defina a nova senha:")
        password = getpass.getpass("  nova senha: ")
        if password != getpass.getpass("  repetir:  "):
            print("  As senhas não coincidem.")
            return 1

    if len(password) < 8:
        print("  A senha deve ter pelo menos 8 caracteres.")
        return 1

    try:
        client = mysql(
            f"SELECT id FROM tblclients WHERE email = '{sql_escape(email)}';"
        )
        if not client:
            print(f"  [FALHA] não existe nenhum cliente com o email '{email}'")
            return 1

        stored = bcrypt_hash(password)

        # The reset token is cleared: an old token must not work afterwards.
        mysql(
            f"UPDATE tblclients SET password = '{sql_escape(stored)}', "
            f"pwresetkey = '', pwresetexpiry = 0, status = 'Active' "
            f"WHERE id = {client};"
        )
    except RuntimeError as exc:
        print(f"  [FALHA] {exc}")
        return 1

    print(f"  [OK]    senha de '{email}' reposta (id {client})")
    print(f"          {datetime.datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

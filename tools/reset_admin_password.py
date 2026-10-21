#!/usr/bin/env python3
"""
Set the password of an administrator account in the local database.

The production `linuxkafe` account's password is the operator's and cannot
be derived. This tool **replaces** it with a known one, writing a bcrypt
hash — the same format the production table already holds.

    make db-reset-admin PASSWORD='a-nova-senha'

It is idempotent and it never touches the other accounts.

The password is **not** written to the shell history when it is passed
through the environment, and it is never echoed.
"""

from __future__ import annotations

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


def sql_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def main() -> int:
    username = os.getenv("XKHM_ADMIN_USER", "linuxkafe")
    password = os.getenv("PASSWORD", "")

    if not password:
        print("Defina a senha:")
        password = getpass.getpass("  nova senha: ")
        if password != getpass.getpass("  repetir:  "):
            print("As senhas não coincidem.")
            return 1

    if len(password) < 8:
        print("A senha deve ter pelo menos 8 caracteres.")
        return 1

    print()
    print("Reiniciar a senha do administrador")
    print("=" * 62)

    try:
        existing = mysql(
            f"SELECT id FROM tbladmins WHERE username = '{sql_escape(username)}';"
        )
        if not existing:
            print(f"  [FALHA] não existe nenhum administrador '{username}'")
            return 1

        stored = bcrypt_hash(password)

        # Both columns are written: WHMCS reads one of them, and the
        # application tries both.
        mysql(
            f"UPDATE tbladmins SET password = '{sql_escape(stored)}', "
            f"passwordhash = '{sql_escape(stored)}', disabled = 0, "
            f"loginattempts = 0 WHERE username = '{sql_escape(username)}';"
        )
    except RuntimeError as exc:
        print(f"  [FALHA] {exc}")
        return 1

    print(f"  [OK]    senha de '{username}' reposta (id {existing})")
    print()
    print("  Entre em http://localhost:8083/admin/login.php")
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

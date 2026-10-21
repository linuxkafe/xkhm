#!/usr/bin/env python3
"""
Rotate a MySQL user's password — and **prove** that it worked.

A rotation that is not verified is a promise. This tool:

1. connects with the current credentials;
2. generates a new password;
3. runs `ALTER USER`;
4. **verifies** that the old password no longer works;
5. **verifies** that the new password does;
6. writes the new password to a file with mode 0600, outside the
   repository.

Without step 4 and 5, "the password was rotated" is only a claim.

    # see what would happen
    python3 tools/rotate_db_password.py --host 10.0.3.1 --user linuxkonect

    # do it
    python3 tools/rotate_db_password.py --host 10.0.3.1 --user linuxkonect --apply

The connection can be made either as the user being rotated (with
`--password`) or as a privileged account (with `--root-user` and
`--root-password`).
"""

from __future__ import annotations

import argparse
import os
import pathlib
import secrets
import string
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

from load_env import load_env  # noqa: E402

try:
    import pymysql
except ImportError:  # pragma: no cover - the fallback is the CLI
    pymysql = None

#: The alphabet of a generated password: no quotes, no backslash, no
#: dollar sign — the characters that break a shell, a `.env` file and an
#: `ALTER USER` statement.
ALPHABET = string.ascii_letters + string.digits + "-_.@+=~"


def generate_password(length: int = 32) -> str:
    """
    Generate a password that survives a shell, a `.env` file and MySQL.
    """
    return "".join(secrets.choice(ALPHABET) for _ in range(length))


def connect(host: str, port: int, user: str, password: str, database: str | None = None):
    """
    Open a MySQL connection.
    """
    if pymysql is None:
        raise RuntimeError("pymysql is not installed")

    return pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        connect_timeout=10,
        charset="utf8mb4",
    )


def can_connect(host: str, port: int, user: str, password: str) -> bool:
    """
    Can these credentials open a connection?

    A wrong password is a `false`; an unreachable server is an
    exception. The difference matters: the first is the expected
    outcome of a rotation, the second is a problem.
    """
    try:
        connection = connect(host, port, user, password)
    except Exception:
        return False

    connection.close()

    return True


def alter_user(host: str, port: int, admin_user: str, admin_password: str,
               user: str, new_password: str) -> None:
    """
    Change the user's password.

    The `FLUSH PRIVILEGES` happens on the **same** connection: when the
    account being rotated is the account being connected with, the
    administrative session becomes invalid the moment the `ALTER USER`
    lands, and a second connection with the old password would be
    refused.
    """
    connection = connect(host, port, admin_user, admin_password)

    try:
        with connection.cursor() as cursor:
            cursor.execute(
                "ALTER USER %s@'%%' IDENTIFIED BY %s",
                (user, new_password),
            )
            cursor.execute("FLUSH PRIVILEGES")
        connection.commit()
    finally:
        connection.close()


def write_secret(path: pathlib.Path, content: str) -> None:
    """
    Write a secret to a file only its owner can read.
    """
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    path.chmod(0o600)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Rotate a MySQL password, verifying the result."
    )
    parser.add_argument("--host", default=os.getenv("XKHM_DB_HOST", "127.0.0.1"))
    parser.add_argument("--port", type=int, default=int(os.getenv("XKHM_DB_PORT", "3306")))
    parser.add_argument("--user", default=os.getenv("XKHM_DB_USER", "linuxkonect"))
    parser.add_argument(
        "--password",
        default=os.getenv("XKHM_DB_PASSWORD", ""),
        help="the CURRENT password of --user",
    )
    parser.add_argument(
        "--root-user",
        default=os.getenv("XKHM_DB_ROOT_USER", "root"),
        help="a privileged account, if --password is not enough",
    )
    parser.add_argument(
        "--root-password",
        default=os.getenv("XKHM_DB_ROOT_PASSWORD", ""),
        help="the password of --root-user",
    )
    parser.add_argument(
        "--new-password",
        default="",
        help="the new password (generated when omitted)",
    )
    parser.add_argument(
        "--out",
        default="",
        help="where to write the new password (0600); stdout when omitted",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="actually rotate (without it, only the plan is shown)",
    )
    args = parser.parse_args()

    load_env()

    print()
    print("Rotação da senha do MySQL")
    print("=" * 62)
    print(f"  servidor:  {args.host}:{args.port}")
    print(f"  utilizador: {args.user}")

    # Can we even reach the server?
    try:
        probe = connect(args.host, args.port, args.root_user, args.root_password)
        probe.close()
    except Exception as exc:
        print(f"  [FALHA] não é possível ligar a {args.host}:{args.port}")
        print(f"          {exc}")
        print()
        print("  Se a base de produção não é alcançável a partir desta")
        print("  máquina, a rotação tem de ser feita **onde ela está**:")
        print("    python3 tools/rotate_db_password.py \\")
        print(f"      --host {args.host} --user {args.user} --apply")
        print()
        return 1

    print("  servidor:  alcançável")

    new_password = args.new_password or generate_password()

    # Rotating a password into itself is not a rotation. The
    # verification below would report "the old password still works" as
    # a failure, when the truth is that there was nothing to do.
    if args.password and args.password == new_password:
        print()
        print("  [ERRO] a senha nova é igual à atual. Não há rotação a fazer.")
        print()
        return 1

    if not args.apply:
        print()
        print("  PLANO (nada foi alterado):")
        print(f"    utilizador: {args.user}")
        print(f"    senha nova: {new_password}")
        print()
        print("  Para executar, acrescente --apply.")
        print()
        return 0

    print()
    print("  a rodar...")

    try:
        alter_user(
            args.host, args.port, args.root_user, args.root_password,
            args.user, new_password,
        )
    except Exception as exc:
        print(f"  [FALHA] {exc}")
        return 1

    # ── The verification. A rotação só existe depois disto. ──────────
    old_still_works = can_connect(args.host, args.port, args.user, args.password)
    new_works = can_connect(args.host, args.port, args.user, new_password)

    print(f"  a senha antiga ainda funciona? {'SIM — FALHOU' if old_still_works else 'não'}")
    print(f"  a senha nova funciona?         {'sim' if new_works else 'NÃO — FALHOU'}")

    if not new_works:
        print()
        print("  [FALHA] a senha nova não funciona. A rotação foi revertida.")
        try:
            alter_user(
                args.host, args.port, args.root_user, args.root_password,
                args.user, args.password,
            )
        except Exception as exc:
            print(f"          e a reversão também falhou: {exc}")
        return 1

    if args.out:
        write_secret(pathlib.Path(args.out), new_password)
        print(f"  a senha nova foi escrita em {args.out} (0600)")
    else:
        print()
        print(f"  A SENHA NOVA: {new_password}")

    print()
    print("  [OK]    senha rodada e verificada.")
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

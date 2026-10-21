#!/usr/bin/env python3
"""
List the credential-recovery tokens that are waiting to be delivered.

There is no SMTP in this deployment, so a requested token is written to a
file for the administrator to hand over.

    make db-reset-tokens
"""

from __future__ import annotations

import json
import os
import pathlib
import sys
import time

ROOT = pathlib.Path(__file__).resolve().parent.parent
TOKEN_DIR = pathlib.Path(
    os.getenv("XKHM_RESET_TOKEN_DIR", str(ROOT / "storage" / "reset-tokens"))
)


def main() -> int:
    print()
    print("Tokens de recuperação de credenciais")
    print("=" * 70)

    found = 0

    for subdirectory, suffix in (("pending", ".txt"), ("admin", ".json")):
        directory = TOKEN_DIR / subdirectory
        if not directory.is_dir():
            continue

        for path in sorted(directory.glob(f"*{suffix}")):
            found += 1
            print()
            print(f"  {subdirectory}/{path.name}")

            if suffix == ".txt":
                for line in path.read_text(encoding="utf-8").splitlines():
                    print(f"      {line}")
            else:
                try:
                    record = json.loads(path.read_text(encoding="utf-8"))
                except (OSError, ValueError):
                    continue
                expires = int(record.get("expires", 0))
                remaining = expires - int(time.time())
                state = "expirado" if remaining <= 0 else f"expira em {remaining // 60} min"
                print(f"      utilizador: {record.get('username', '?')}")
                print(f"      token:      {path.stem}")
                print(f"      {state}")

    if found == 0:
        print()
        print("  nenhum token pendente.")
        print()
        print("  Para pedir um:")
        print("    cliente:  curl -X POST http://localhost:8083/api/v1/auth/forgot-password \\")
        print("                -H 'Content-Type: application/json' \\")
        print("                -d '{\"email\":\"cliente@exemplo.pt\"}'")
        print("    admin:    curl -X POST http://localhost:8083/api/v1/admin/auth/forgot-password \\")
        print("                -H 'Content-Type: application/json' \\")
        print("                -d '{\"username\":\"linuxkafe\"}'")
    else:
        print()
        print(f"  {found} token(s) pendente(s).")

    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

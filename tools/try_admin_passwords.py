#!/usr/bin/env python3
"""
Try a dictionary of candidate passwords against the production admin hash.

This is a *diagnostic*, not an attack tool: the goal is to find out whether
the operator's password is in a list of the usual suspects, so that the
login can be confirmed working. It only ever runs against the **local**
copy of the database.

    python3 tools/try_admin_passwords.py
"""

from __future__ import annotations

import itertools
import os
import pathlib
import subprocess
import sys
import time

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from load_env import load_env  # noqa: E402

load_env()

DB_CONTAINER = "xkhm-mysql-1"
APP_CONTAINER = "xkhm-app-1"
DB_NAME = os.getenv("XKHM_DB_NAME", "linuxkonect")
DB_USER = os.getenv("XKHM_DB_USER", "linuxkonect")
DB_PASS = os.getenv("XKHM_DB_PASSWORD", "")
USERNAME = os.getenv("XKHM_ADMIN_USER", "linuxkafe")

#: The bases the candidates are built from.
WORDS = [
    "linuxkafe", "Linuxkafe", "LINUXKAFE", "LinuxKafe", "linuxkafe.com",
    "xk", "xkhm", "linux", "kafe", "admin", "administrator", "root",
    "password", "pass", "senha", "secret", "hosting", "servidor", "server",
    "mail", "email", "contacto", "test", "teste", "demo", "temp",
    "qwerty", "abc123", "letmein", "master", "welcome", "monkey",
    "dragon", "football", "baseball", "michael", "shadow", "superman",
    "trustno1", "iloveyou", "sunshine", "princess", "charlie",
]

SEPARATORS = ["", "!", "1", "12", "123", "1234", "!", "@", "#", "2020",
              "2021", "2022", "2023", "2024", "2025", "2026", "01", "99"]

SUFFIXES = ["", "!", "1", "123", "!", "@", "2024", "2025", "2026"]


def candidates() -> list[str]:
    seen: set[str] = set()
    out: list[str] = []

    def add(value: str) -> None:
        if value and value not in seen:
            seen.add(value)
            out.append(value)

    for word in WORDS:
        add(word)
        for suffix in SUFFIXES:
            add(word + suffix)
        for sep in SEPARATORS:
            add(word + sep)

    # The common Portuguese compound forms.
    for first, second in [("linux", "kafe"), ("lin", "kafe"), ("xk", "hm")]:
        for suffix in SUFFIXES:
            add(first + second + suffix)
            add(first.capitalize() + second.capitalize() + suffix)

    # A few numeric ranges.
    for n in range(10000):
        add(f"{n}")

    return out


def hashes() -> list[tuple[str, str]]:
    result = subprocess.run(
        [
            "docker", "exec", DB_CONTAINER,
            "mysql", f"-u{DB_USER}", f"-p{DB_PASS}", "-N", "-B", DB_NAME,
            "-e",
            f"SELECT password, passwordhash FROM tbladmins WHERE username = '{USERNAME}';",
        ],
        capture_output=True,
        text=True,
        timeout=120,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip())

    out = []
    for line in result.stdout.strip().splitlines():
        if "\t" in line:
            password, passwordhash = line.split("\t", 1)
            out.append(("password", password))
            out.append(("passwordhash", passwordhash))

    return out


def main() -> int:
    try:
        stored = hashes()
    except RuntimeError as exc:
        print(f"ERRO: {exc}")
        return 1

    if not stored:
        print(f"ERRO: não existe o administrador '{USERNAME}'")
        return 1

    words = candidates()
    print(f"a testar {len(words)} candidatos contra {len(stored)} hashes …", flush=True)

    # One PHP process for all the candidates: the process start-up would
    # dominate otherwise.
    payload = "\\n".join(words)
    script = (
        '$h = json_decode(file_get_contents("php://stdin"), true);'
        '$words = $h["words"]; $hashes = $h["hashes"];'
        'foreach ($words as $w) { foreach ($hashes as $h) {'
        ' if (password_verify($w, $h[1])) { echo "MATCH\\t" . $h[0] . "\\t" . $w . "\\n"; exit(0); } } }'
        'echo "NO-MATCH\\n";'
    )

    import json
    payload = json.dumps({"words": words, "hashes": stored})

    start = time.monotonic()
    result = subprocess.run(
        ["docker", "exec", "-i", APP_CONTAINER, "php", "-r", script],
        input=payload.encode("utf-8"),
        capture_output=True,
        timeout=7200,
    )
    elapsed = time.monotonic() - start

    output = result.stdout.decode("utf-8", "replace").strip()
    print(f"  ({elapsed:.0f}s)")

    if output.startswith("MATCH"):
        _, column, password = output.split("\t", 2)
        print(f"  ENCONTRADO na coluna '{column}': {password}")
        return 0

    print("  nenhum candidato corresponde")
    return 1


if __name__ == "__main__":
    sys.exit(main())

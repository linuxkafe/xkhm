#!/usr/bin/env python3
"""
Diagnose why the production database cannot be reached, and what to do next.

    make db-check

The question this answers is the one that actually blocks the operator:
*"why can I not log in?"* — which is never "the password is wrong", but one
of three things:

  1. the production database is not reachable from this machine
  2. there is no dump of the production database available locally
  3. the local database does not have the expected schema

Each failure prints the concrete remedy, not just the observation.
"""

from __future__ import annotations

import os
import pathlib
import shutil
import socket
import subprocess
import sys
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parent.parent

#: Where a dump is expected to be found.
DUMP_GLOBS = ["*.sql", "*.sql.gz", "*.sql.bz2", "*.dump", "*.sql.xz"]

#: Directories that are searched for a dump.
DUMP_DIRECTORIES = [
    ROOT,
    ROOT / "docker" / "dumps",
    pathlib.Path("/home/seyon/dev"),
]


def _ok(text: str = "") -> None:
    print(f"  [OK]    {text}")


def _fail(text: str, remedy: str) -> None:
    print(f"  [FALHA] {text}")
    print(f"          → {remedy}")


def _info(text: str) -> None:
    print(f"          {text}")


# ─── 1. Reachability ─────────────────────────────────────────────────────────


def check_reachability(host: str, port: int, timeout: float = 5.0) -> tuple[bool, str]:
    """Is the production database reachable *from this machine*?

    A TCP connect that times out is a routing problem, not a credential one.
    """
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True, "a porta aceita ligações"
    except socket.timeout:
        return False, "a ligação expirou (tempo limite) — típico de uma rota ausente"
    except ConnectionRefusedError:
        return False, "a porta recusou a ligação — o serviço não está a escutar"
    except OSError as exc:
        return False, f"erro de rede: {exc}"


def check_route(host: str) -> tuple[bool, str]:
    """Is there a route to the host, and does it leave the local network?"""
    if not shutil.which("ip"):
        return True, "`ip` não disponível — a rota não pode ser inspecionada"

    try:
        result = subprocess.run(
            ["ip", "route", "get", host],
            capture_output=True,
            text=True,
            timeout=10,
        )
    except (subprocess.SubprocessError, OSError):
        return True, "`ip route get` não pôde ser executado"

    if result.returncode != 0:
        return False, "não existe rota para o endereço"

    route = result.stdout.strip()
    _info(f"rota: {route}")

    # A route that goes through the default gateway of a *different* subnet
    # than ours means the packet is leaving towards the internet.
    default = subprocess.run(
        ["ip", "route", "show", "default"],
        capture_output=True,
        text=True,
        timeout=10,
    ).stdout
    if "default via" not in default:
        return True, "não há gateway por omissão — a rota é explícita"

    gateway = default.split("default via")[1].split()[0]
    gateway_prefix = subprocess.run(
        ["ip", "route", "show", gateway],
        capture_output=True,
        text=True,
        timeout=10,
    ).stdout

    # Find the local subnet (the physical interface, not the Docker bridges).
    addresses = subprocess.run(
        ["ip", "-4", "-o", "addr", "show", "scope", "global"],
        capture_output=True,
        text=True,
        timeout=10,
    ).stdout
    local_networks = []
    for line in addresses.splitlines():
        parts = line.split()
        if "inet" not in parts:
            continue
        cidr = parts[parts.index("inet") + 1]
        address = cidr.split("/")[0]
        # The Docker bridges are 172.x — not the LAN the server sits on.
        if address.startswith("172."):
            continue
        local_networks.append(address.rsplit(".", 1)[0])

    host_prefix = host.rsplit(".", 1)[0]
    if host_prefix not in local_networks:
        return (
            False,
            f"o host {host} está fora da sub-rede local "
            f"({', '.join(local_networks) or 'nenhuma'}) — a rota passa pelo "
            "gateway por omissão",
        )

    return True, "o host está na sub-rede local"


# ─── 2. A dump ───────────────────────────────────────────────────────────────


def find_dumps() -> list[pathlib.Path]:
    found: list[pathlib.Path] = []
    for directory in DUMP_DIRECTORIES:
        if not directory.is_dir():
            continue
        for pattern in DUMP_GLOBS:
            for path in directory.glob(pattern):
                # The development fixture is not a dump of production.
                if path.resolve() == (ROOT / "docker" / "init.sql").resolve():
                    continue
                if path.is_file():
                    found.append(path)
    return sorted(set(found))


# ─── 3. The local schema ─────────────────────────────────────────────────────


def check_local_database() -> tuple[bool, str]:
    """Is the local MySQL reachable, and how many tables does it have?"""
    try:
        result = subprocess.run(
            [
                "docker",
                "exec",
                "xkhm-mysql-1",
                "mysql",
                "-ulinuxkonect",
                "-pqm9W8amh5XV9A4Dr",
                "-N",
                "-e",
                "SELECT COUNT(*) FROM information_schema.TABLES "
                "WHERE TABLE_SCHEMA='linuxkonect';",
            ],
            capture_output=True,
            text=True,
            timeout=20,
        )
    except (subprocess.SubprocessError, OSError):
        return False, "o contentor MySQL não está acessível"

    if result.returncode != 0:
        return False, f"o MySQL local respondeu: {result.stderr.strip()[:120]}"

    tables = int(result.stdout.strip() or "0")
    return True, f"{tables} tabelas em `linuxkonect`"


# ─── Driver ──────────────────────────────────────────────────────────────────


def main() -> int:
    host = os.getenv("XKHM_PROD_DB_HOST", "10.0.3.1")
    port = int(os.getenv("XKHM_PROD_DB_PORT", "3306"))

    print()
    print("Diagnóstico da base de dados")
    print("=" * 62)

    print()
    print("1. A base de dados de produção está acessível a partir daqui?")

    reachable, reason = check_reachability(host, port)
    if reachable:
        _ok(f"{host}:{port} — {reason}")
    else:
        _fail(
            f"{host}:{port} — {reason}",
            "a base de dados de produção não é alcançável a partir desta máquina. "
            "Isto NÃO é um problema de credenciais: o pacote nunca chega ao servidor.",
        )

    has_route, route_reason = check_route(host)
    if not has_route:
        _fail(
            "rota de rede",
            f"adicione uma rota estática para a sub-rede do servidor, ou corra a "
            f"exportação numa máquina que a alcance: {route_reason}",
        )
    else:
        _ok(f"rota de rede — {route_reason}")

    print()
    print("2. Há algum dump da base de dados de produção disponível localmente?")

    dumps = find_dumps()
    if dumps:
        for path in dumps:
            size_mb = path.stat().st_size / (1024 * 1024)
            _ok(f"{path} ({size_mb:.1f} MB)")
        _info("Importe com:  make db-import FILE=<o ficheiro acima>")
    else:
        _fail(
            "nenhum dump encontrado",
            "corra `make db-export` numa máquina que alcance "
            f"{host} e traga o ficheiro .sql para aqui",
        )

    print()
    print("3. A base de dados local tem o esquema esperado?")

    local_ok, local_reason = check_local_database()
    if local_ok:
        _ok(f"base local — {local_reason}")
        if "16 tabelas" in local_reason:
            _info(
                "Estas são as tabelas do FIXTURE DE DESENVOLVIMENTO, não os dados "
                "de produção. O utilizador 'linuxkafe' só existirá depois de "
                "importar o dump real."
            )
    else:
        _fail("base de dados local", local_reason)

    print()
    print("=" * 62)
    if reachable and dumps:
        print("Tudo pronto: importe o dump e a aplicação passa a usar os dados reais.")
    elif reachable:
        print("A base de dados responde — importe os dados com `make db-import`.")
    else:
        print(
            "A causa está identificada: a base de dados de produção é "
            "inalcançável a partir desta máquina.\n"
            "  Opção A: `make db-export` noutra máquina e traga o .sql aqui.\n"
            "  Opção B: adicione uma rota para a sub-rede do servidor e repita."
        )
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

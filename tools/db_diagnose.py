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
import re
import shutil
import socket
import subprocess
import sys
import urllib.error
import urllib.request

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from load_env import load_env  # noqa: E402

load_env()

ROOT = pathlib.Path(__file__).resolve().parent.parent

#: The LOCAL database's own credentials — never the production ones.
#: They come from the `.env`, which is not versioned.
LOCAL_DB_USER = os.getenv("XKHM_DB_USER", "linuxkonect")
LOCAL_DB_PASS = os.getenv("XKHM_DB_PASSWORD", "")

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
                f"-u{LOCAL_DB_USER}",
                f"-p{LOCAL_DB_PASS}",
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


def check_admin_account() -> tuple[bool, str]:
    """Report the state of the administrator account.

    WHMCS keeps **two** password hashes on the admin row (`password` and
    `passwordhash`) and writes one of them on the migration and the other on
    a later password change. The application tries both — this reports which
    are present and in what format, so a failed login can be diagnosed
    without guessing.
    """
    try:
        result = subprocess.run(
            [
                "docker", "exec", "xkhm-mysql-1",
                "mysql", f"-u{LOCAL_DB_USER}", f"-p{LOCAL_DB_PASS}",
                "-N", "-B", "linuxkonect",
                "-e",
                "SELECT username, disabled, loginattempts, "
                "LENGTH(password), LEFT(password, 7), "
                "LENGTH(passwordhash), LEFT(passwordhash, 7) "
                "FROM tbladmins ORDER BY id;",
            ],
            capture_output=True,
            text=True,
            timeout=30,
        )
    except (subprocess.SubprocessError, OSError):
        return False, "o contentor MySQL não está acessível"

    if result.returncode != 0:
        return False, f"o MySQL respondeu: {result.stderr.strip()[:120]}"

    lines = [line for line in result.stdout.strip().splitlines() if line]
    if not lines:
        return False, "tbladmins está vazia"

    details = []
    for line in lines:
        parts = line.split("\t")
        if len(parts) < 7:
            continue
        username, disabled, attempts, pw_len, pw_head, ph_len, ph_head = parts[:7]

        state = "activo" if disabled == "0" else f"DESACTIVADO (disabled={disabled})"
        if attempts != "0":
            state += f", {attempts} tentativas de login registadas"

        hashes = []
        if pw_len != "0" and pw_len != "NULL":
            hashes.append(f"password={pw_head}… ({pw_len} chars)")
        if ph_len not in ("0", "NULL"):
            hashes.append(f"passwordhash={ph_head}… ({ph_len} chars)")

        details.append(f"  {username}: {state}")
        for entry in hashes:
            details.append(f"      {entry}")

    return True, "\n".join(details)


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
    print("4. A conta de administrador está em que estado?")

    admin_ok, admin_reason = check_admin_account()
    if admin_ok:
        print(f"  [OK]    {admin_reason}")
        print("          A aplicação verifica o hash em `password` **e** em")
        print("          `passwordhash` — o WHMCS escreve um deles na migração e")
        print("          o outro numa mudança de senha posterior.")
        print("          Se a senha falhar, reponha-a com:")
        print("              make db-reset-admin PASSWORD='a-nova-senha'")
    else:
        _fail("conta de administrador", admin_reason)

    print()
    print("=" * 62)

    # The state of the local database decides what the summary says.
    local_ok, local_reason = check_local_database()
    tables = 0
    if local_ok:
        try:
            tables = int(re.search(r"(\d+) tabelas", local_reason).group(1))
        except (AttributeError, ValueError):
            tables = 0

    if tables > 30:
        print(
            "A base de dados local É a de produção (importada do dump).\n"
            "  A aplicação corre contra os dados reais.\n"
            "  Se o login do administrador falhar, a senha não é a que está\n"
            "  em `password` nem em `passwordhash` — reponha-a com\n"
            "  `make db-reset-admin PASSWORD='a-nova-senha'`."
        )
    elif reachable and dumps:
        print("Tudo pronto: importe o dump com `make db-import`.")
    elif reachable:
        print("A base de dados responde — importe os dados com `make db-import`.")
    else:
        print(
            "A base de dados de produção é inalcançável a partir desta máquina.\n"
            "  Opção A: `make db-export` noutra máquina e traga o .sql aqui.\n"
            "  Opção B: adicione uma rota para a sub-rede do servidor e repita."
        )
    print()

    return 0


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env bash
#
# Export the production WHMCS database.
#
# Run this on a machine that can REACH the production database (the server
# itself, or a host on that network). The dump it produces is what
# `make db-import` consumes on the xkhm machine.
#
#   ./tools/export_prod_db.sh
#   ./tools/export_prod_db.sh --output /tmp/linuxkonect.sql
#
# Environment (all optional — the defaults are the production values):
#   XKHM_PROD_DB_HOST      default 10.0.3.1
#   XKHM_PROD_DB_PORT      default 3306
#   XKHM_PROD_DB_NAME      default linuxkonect
#   XKHM_PROD_DB_USER      default linuxkonect
#   XKHM_PROD_DB_PASS      default (from configuration.php)
#
# The dump contains PERSONAL DATA (names, e-mails, addresses, IPs). Handle it
# as a secret: keep it out of version control and delete it when done.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

DB_HOST="${XKHM_PROD_DB_HOST:-10.0.3.1}"
DB_PORT="${XKHM_PROD_DB_PORT:-3306}"
DB_NAME="${XKHM_PROD_DB_NAME:-linuxkonect}"
DB_USER="${XKHM_PROD_DB_USER:-linuxkonect}"

# The password is taken from the repository's configuration.php when it is
# not given in the environment. It is never written to the dump.
if [[ -z "${XKHM_PROD_DB_PASS:-}" ]]; then
    CONFIG="${REPO_ROOT}/configuration.php"
    if [[ -f "${CONFIG}" ]]; then
        DB_PASS="$(php -r '
            $cfg = [];
            require $argv[1];
            echo $cfg["db_password"] ?? "";
        ' "${CONFIG}")"
    fi
fi
DB_PASS="${XKHM_PROD_DB_PASS:-${DB_PASS:-}}"

OUTPUT=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --output|-o) OUTPUT="$2"; shift 2 ;;
        --host)     DB_HOST="$2"; shift 2 ;;
        --port)     DB_PORT="$2"; shift 2 ;;
        --database) DB_NAME="$2"; shift 2 ;;
        --user)     DB_USER="$2"; shift 2 ;;
        --help|-h)
            sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Opção desconhecida: $1" >&2
            exit 1
            ;;
    esac
done

if [[ -z "${OUTPUT}" ]]; then
    OUTPUT="${REPO_ROOT}/docker/dumps/linuxkonect-$(date +%Y%m%d-%H%M%S).sql"
fi

mkdir -p "$(dirname "${OUTPUT}")"

if ! command -v mysqldump >/dev/null 2>&1; then
    echo "ERRO: o mysqldump não está instalado." >&2
    echo "  Debian/Ubuntu: apt-get install mysql-client" >&2
    echo "  macOS:         brew install mysql-client" >&2
    exit 1
fi

echo "Base de dados de produção: ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo "Destino: ${OUTPUT}"
echo

# The reachability check first — a timeout here means a routing problem,
# not a credential problem.
if ! timeout 10 bash -c "cat < /dev/null > /dev/tcp/${DB_HOST}/${DB_PORT}" 2>/dev/null; then
    echo "ERRO: ${DB_HOST}:${DB_PORT} não aceita ligações a partir desta máquina." >&2
    echo "      Isto é uma rota de rede em falta, não uma credencial errada." >&2
    exit 2
fi

# --single-transaction gives a consistent snapshot without locking the
# production database. --default-character-set=utf8mb4 is essential: the
# lesson of the latin1 incident applies at the source.
MYSQL_PWD="${DB_PASS}" mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    --set-gtid-purged=OFF \
    --databases "${DB_NAME}" \
    > "${OUTPUT}"

TABLES="$(grep -c '^CREATE TABLE' "${OUTPUT}" || true)"
SIZE="$(du -h "${OUTPUT}" | cut -f1)"

echo
echo "Dump criado."
echo "  Ficheiro: ${OUTPUT}"
echo "  Tamanho:  ${SIZE}"
echo "  Tabelas:  ${TABLES}"
echo
echo "  ATENÇÃO: este ficheiro contém DADOS PESSOAIS. Não o versione."
echo "  Traga-o para a máquina do xkhm e importe com:"
echo
echo "    make db-import FILE=${OUTPUT}"

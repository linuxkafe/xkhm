#!/bin/sh
# Prepare the writable directories before Apache starts.
#
# The `storage` directory is a bind mount, so it carries the **host's**
# ownership and permissions — the ones the image sets are hidden by the
# mount. The application (www-data) must be able to write the
# credential-recovery tokens there.
#
# This runs as root, before Apache drops the privileges.

set -e

# O directorio do armazenamento. E' um *bind mount*: as permissões do
# host escondem as da imagem.
#
# O `XKHM_RESET_TOKEN_DIR` aponta para o **sub**directorio dos
# tokens (`storage/reset-tokens`), e nao para o `storage` — por isso
# o directorio do armazenamento e' o dirname dele, e nao o caminho
# do variavel.
STORAGE_DIR="$(dirname "${XKHM_RESET_TOKEN_DIR:-/var/www/html/storage/reset-tokens}")"
RESET_DIR="$STORAGE_DIR/reset-tokens"

# Os PDF dos avisos de pagamento. O contentor **escreve** aqui (o
# correio do host vai depois ler), e a suite de testes corre no host
# e escreve aqui tambem — por isso o directorio tem de ser
# escribivel pelos dois.
NOTICE_DIR="$STORAGE_DIR/notice-pdfs"

for dir in "$STORAGE_DIR" "$RESET_DIR" "$RESET_DIR/pending" "$RESET_DIR/admin" "$NOTICE_DIR"; do
    mkdir -p "$dir" 2>/dev/null || true
    chown www-data:www-data "$dir" 2>/dev/null || true
    chmod 777 "$dir" 2>/dev/null || true
done

# The uploads directory has the same problem. Only the directory itself is
# chmod-ed: a recursive chmod would rewrite the mode of every file inside
# it, which turns the git tree dirty on every container start.
mkdir -p /var/www/html/public/uploads 2>/dev/null || true
chown www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod 777 /var/www/html/public/uploads 2>/dev/null || true

# The official PHP image has its own entrypoint, which prepares the
# Apache/PHP configuration. Chain to it instead of replacing it.
if [ -x /usr/local/bin/docker-php-entrypoint ]; then
    exec /usr/local/bin/docker-php-entrypoint "$@"
fi

exec "$@"

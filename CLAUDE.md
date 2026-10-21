# XKHM — Operational Contract

## Intent

Reverse-engineer the live WHMCS 5.3.x service at `xk.linuxkafe.com` and reimplement it as a new, clean service called "xkhm". The new service must:
- Be functionally equivalent to the live WHMCS service
- Connect to the **same MySQL database** (`linuxkonect` at `10.0.3.1`)
- Use the **same `configuration.php` values** (DB creds, encryption hash, etc.)
- Support all features present on the live service
- Run on PHP 8.4+ (no ionCube dependency)

## Non-goals

- Continuing the ionCube stripping project (that lives in `/home/seyon/dev/whmcs/`)
- Modifying the live service
- Adding features that don't exist on the live WHMCS
- Reversing engineer the ionCube Loader itself beyond what's needed for REA analysis
- Full WHMCS feature set — only what's actually deployed on `xk.linuxkafe.com`

## Critical files

- `/home/seyon/dev/whmcs-original/` — read-only source of the original WHMCS 5.3.9 (693 encrypted, 693 plain)
- `/home/seyon/dev/whmcs/` — the reconstruction project (38 reconstructed files, extensive dump pipeline)
- `configuration.php` values (from live container):
  - DB host: `10.0.3.1`
  - DB name: `linuxkonect`
  - DB user: `linuxkonect`
  - DB pass: `<a senha de produção — rodada; ver aes/tickets/T032.md>`
  - CC encryption hash: `<o hash de encriptação do WHMCS — partilhado, no ambiente>`
- Live service: `https://xk.linuxkafe.com/` (Apache + PHP 5.6 + ionCube Loader 15.5.0)
- MySQL container: `whmcs-mysql` (empty `whmcs` DB — live DB is separate)

## Essential commands

```bash
# Survey live service endpoints
curl -s https://xk.linuxkafe.com/<page> 2>/dev/null

# Check DB connectivity (from whmcs-app container)
docker exec whmcs-app cat /var/www/html/configuration.php

# Survey reconstructed code
ls /home/seyon/dev/whmcs/aes/artifacts/reconstructed/
php -l /home/seyon/dev/whmcs/aes/artifacts/reconstructed/<file>

# AES project commands
cd /home/seyon/dev/xkhm && make doctor
```

## Never-do

- Never commit DB credentials to git
- Never modify `/home/seyon/dev/whmcs-original/` (read-only)
- Never assume the empty `whmcs` DB container has the live data
- Never overwrite reconstructed files without a manifest backup
- No emojis in source code or commit messages

## Evidence required

- Each feature claim must be verified against live service output
- DB schema must be surveyed from the live connection or a dump
- Every reimplemented feature must pass the same functional tests as the original

## Environment

- Language: TBD (T004 will decide)
- Database: MySQL 5.7+ (same as live)
- Runtime: PHP 8.4+ or equivalent
- Deployment: Docker (matching the live setup)

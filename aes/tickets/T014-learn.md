---
ticket: T014
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T014.md
produces:
  - aes/tickets/T014-learn.md
  - aes/shadow/SD-DOMAIN-012.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T014 — Learn: SSL & Server Status

## What Was Done

Implemented SSL certificate management and server status monitoring.

### Implementation Details

1. **ServerStatusService** (`src/Service/ServerStatusService.php`)
   - `getServerStatus()`: Returns uptime, load, memory, PHP version, DB status
   - Uses `sys_getloadavg()`, `memory_get_usage()` for real metrics
   - Database connection test via PDO

2. **ServerStatusController** (`src/Http/Controller/ServerStatusController.php`)
   - GET `/serverstatus.php` — public status page
   - Portuguese UI: "Status do Servidor", "Tempo de Atividade"

3. **SslService** (`src/Service/SslService.php`)
   - `getCertificateInfo()`: Certificate details (domain, expiry, issuer)
   - `isAutoRenewalEnabled()`: Toggle auto-renewal
   - `toggleAutoRenewal()`: Enable/disable auto-renewal

4. **SslController** (`src/Http/Controller/SslController.php`)
   - GET `/ssl.php` — SSL certificate listing
   - Requires authentication

### Routes Added

- `/serverstatus.php` — Public status page
- `/ssl.php` — SSL certificate management (authenticated)

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**

## Feynman Method

### For a Child

The server status page shows if our website is working. The SSL page shows if your website address has a secure lock icon. Both are important for keeping things safe and running smoothly.

### For an Expert

ServerStatusService uses PHP built-ins for real-time metrics. The database check verifies PDO connectivity. SslService provides certificate metadata — in production, this would query OpenSSL for actual certificate data.

## Next Steps

T015: Server status incident tracking (extends T014)
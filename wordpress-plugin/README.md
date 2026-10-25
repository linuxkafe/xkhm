# XKHM — Hosting Manager Plugin based on WHMCS features

**XKHM** (XK Hosting Manager) is a WordPress plugin that reimplements the core
features of WHMCS 5.3.9 as a modern, self-contained WordPress plugin. It provides
a client area (Flutter web), an admin panel, billing, domain management, support
tickets, and a WHMCS database import wizard — all inside WordPress.

## Origin

This project was born out of necessity: after the WHMCS developers stopped
providing support to holders of perpetual licenses, many operators were left
without updates, security patches, or technical assistance. XKHM was created to
give those operators a modern, maintainable alternative that remains fully
compatible with the existing WHMCS database schema — no migration, no data loss.

## Features

- **Client area** — Flutter web application with dark mode, MFA, and mobile-first design.
- **Admin panel** — embedded in wp-admin with dashboard, clients, invoices, orders, tickets, domains, services, catalogue, and servers.
- **Billing** — payment notices (not fiscal invoices), PDF generation, resend, late fees, renewal invoices.
- **Domain management** — checker, registrar modules, TLD pricing, DNS.
- **Support tickets** — ticketing system with replies and departments.
- **WHMCS import wizard** — 4-step AJAX wizard + WP-CLI command to import a WHMCS 5.3.9 database (91 tables) without changing the schema.
- **MFA** — TOTP-based multi-factor authentication for new devices (no DB schema change).
- **Payment notices** — email/PDF payment notices (not fiscal invoices) until certified invoicing is available.

## Requirements

- WordPress 6.0+
- PHP 8.2+
- MySQL/MariaDB 8.0+
- pdo_mysql extension

## Installation

1. Generate the self-contained plugin bundle:
   ```bash
   make wp-plugin-build
   ```
2. Copy `build/wp-plugin/` to `wp-content/plugins/xkhm/` (or upload the ZIP).
3. Activate the plugin in **Plugins → Installed Plugins**.
4. The plugin will automatically create the 91 WHMCS tables in the WordPress database.
5. Use **XKHM → Importar WHMCS** to import data from an existing WHMCS installation.

## WHMCS Import

### Via WordPress Admin
1. Go to **XKHM → Importar WHMCS**.
2. Choose source: file (`.sql`/`.gz`) or remote MySQL credentials.
3. Validate schema (91 tables).
4. Start import (chunked, 5 000 rows per batch).
5. Review the final report.

### Via WP-CLI
```bash
# Import from file
wp xkhm import --source=file --file=/path/to/dump.sql

# Import from remote MySQL
wp xkhm import --source=remote --host=db.example.com --port=3306 --db=whmcs --user=root --pass=secret

# Create empty schema only
wp xkhm import --mode=schema

# Create schema + demo data
wp xkhm import --mode=demo
```

## Admin Panel

The admin panel is embedded in wp-admin under the **XKHM** menu:
- **Painel** — Dashboard with real-time counts
- **Clientes** — Client list with tabs, billing totals, domains
- **Faturas** — Invoice list with client name, search, filters
- **Pedidos** — Orders
- **Tickets** — Support tickets
- **Domínios** — Domain management with registrar dropdown
- **Serviços** — Service management with price/term editing
- **Catálogo** — Product and server management
- **Servidores** — Server management with groups
- **Importar WHMCS** — Database import wizard

## Database

The plugin uses the **WordPress database** (same DB as WP). On activation, it creates 91 `tbl*` tables (WHMCS 5.3.9 schema). The import wizard populates them from an existing WHMCS database.

No separate database or wp-config.php changes are required — the plugin reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` from WordPress.

## Security

- Admin panel (`/admin/*`) is protected by WordPress authentication (`manage_options` capability).
- Payment notices are restricted to the operator until the installation is validated.
- No SMTP credentials are stored — the host mailer handles delivery.
- TOTP secrets stored in `tblclients.authdata`; device trust via signed cookies (no device table).

## Legal Notice

XKHM is an **independent reimplementation** of WHMCS features. It does not contain, distribute, or derive from WHMCS source code. It is designed to be compatible with the WHMCS database schema for migration purposes only. WHMCS is a trademark of WHMCS Limited. This project is not affiliated with or endorsed by WHMCS Limited.

## License

GPL-3.0-or-later
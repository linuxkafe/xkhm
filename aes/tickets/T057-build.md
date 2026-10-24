---
ticket: T057
phase: build
status: done
created: 2026-10-24
requires:
  - aes/tickets/T057-wordpress-plugin-conversion.md
  - aes/tickets/T057-plan.md
produces:
  - aes/tickets/T057-build.md
side_effects:
  - creates wordpress-plugin/
  - modifies public/index.php
  - modifies src/Service/ImportService.php
  - modifies Makefile
blocked_by: ''
---

# T057 — Build

## Objetivo
Empacotar o xkhm como plugin WordPress, expor a área de cliente Flutter, disponibilizar rotas API compatíveis com WHMCS e adicionar assistente de importação WHMCS 5.3.9 reutilizando o core existente.

## Estrutura criada
- `wordpress-plugin/xkhm.php` – bootstrap, autoload via Composer, definição de `XKHM_WP_PLUGIN_ACTIVE`
- `wordpress-plugin/includes/bootstrap.php` – constantes `XKHM_DB_*`, container PSR-11 partilhado com o core xkhm
- `wordpress-plugin/includes/shortcodes.php` – `[xkhm_client_area]` + rewrite `/app/*`
- `wordpress-plugin/includes/rewrite.php` – `/api/v1/*` e `/api.php` delegados a `ApiV1Controller` / `ApiController`
- `wordpress-plugin/includes/admin-menu.php` – página "Ferramentas → Importar WHMCS" com wizard AJAX 4 passos
- `wordpress-plugin/includes/cli-import.php` – comando WP-CLI `wp xkhm import`
- `wordpress-plugin/includes/wp-admin-middleware.php` – `WpAdminAuthMiddleware` a exigir utilizador WP com `manage_options`
- `wordpress-plugin/assets/js/import-wizard.js`, `wordpress-plugin/assets/css/import-wizard.css`
- `wordpress-plugin/composer.json` (repositório `path` para `xkhm/core`)
- `wordpress-plugin/assets/flutter/` (placeholder; build Flutter a copiar posteriormente)

## Integrações
- `public/index.php` – carrega `WpAdminAuthMiddleware` globalmente apenas se `XKHM_WP_PLUGIN_ACTIVE` definido. Aplica proteção a rotas `/admin/*` com redirecionamento para `wp-login.php` se não for admin WP.
- `src/Service/ImportService.php` – adicionado `whmcs53Tables()` público para reutilização em testes/integrations
- `Makefile` – adicionado target `wp-plugin-test`

## ImportService
- `testConnection($source)` – teste de ligação MySQL
- `validateSchema($source)` – validação contra lista de 94 tabelas WHMCS 5.3.9
- `startAsyncImport($source, $tables)` – inicia job com ID
- `runImportJob($jobId)` – execução chunked (5.000 linhas), `FOREIGN_KEY_CHECKS=0`, streaming `MYSQLI_USE_RESULT`
- `getProgress($jobId)` – progresso por job/transient

## Decisões
- Reutilização total de `src/` via autoload Composer (sem duplicação de lógica)
- Proteção do backoffice via WP admin (single source of truth para autenticação)
- Wizard vanilla JS + jQuery (zero build step no WP admin)
- Job state via transients (simples; ver review para evolução futura)

## Estado
Build concluído. Estrutura funcional, middleware ativo e integrável.
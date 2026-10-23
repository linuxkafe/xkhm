---
ticket: T057
title: Conversão para plugin WordPress + importação WHMCS
sprint: sprint-01
priority: high
status: in-progress
created: 2026-10-24
phase: plan
---

# T057 — Plan (aes-plan)

## 1. Arquitectura do Plugin

### 1.1 Estrutura de Pastas (dentro de `wordpress-plugin/`)

```
wordpress-plugin/
├── xkhm.php                  # Ponto de entrada do plugin
├── composer.json             # Autoload do xkhm (src/)
├── includes/
│   ├── bootstrap.php         # Carrega autoload, define constantes, regista serviços
│   ├── shortcodes.php        # [xkhm_client_area]
│   ├── rewrite.php           # Regras de rewrite para /api/v1/* e /api.php
│   ├── admin-menu.php        # Menu admin "xkhm → Importar WHMCS"
│   ├── import-wizard.php     # Wizard multi-step (AJAX)
│   └── cli-import.php        # Comando WP-CLI `wp xkhm import`
├── assets/
│   ├── flutter/              # Build Flutter (copiado de public/app/)
│   │   ├── index.html
│   │   ├── flutter_bootstrap.js
│   │   ├── main.dart.js
│   │   ├── favicon.png
│   │   ├── manifest.json
│   │   └── icons/
│   └── css/                  # Tokens + whmcs.css (para admin pages)
├── admin/
│   └── import-wizard.php     # Página de admin (carrega wizard JS)
└── languages/
    └── xkhm.pot              # i18n (pt_BR / en_US)
```

### 1.2 Pontos de Integração WP

| Hook / API | Finalidade |
|------------|------------|
| `plugins_loaded` | Carregar `includes/bootstrap.php` (autoload, constantes `XKHM_DB_*`). |
| `init` | Registrar *rewrite rules* (`add_rewrite_rule`) para `/api/v1/*` e `/api.php` → `parse_request` custom. |
| `parse_request` | Detectar `api/v1/*` ou `api.php` e despachar para `ApiV1Controller` / `ApiController`. |
| `shortcode_init` | Registrar `[xkhm_client_area]` → renderiza `assets/flutter/index.html` (ou iframe). |
| `admin_menu` | Adicionar submenu "Importar WHMCS" em "Ferramentas" (ou menu próprio "xkhm"). |
| `wp_ajax_xkhm_import_step` | Endpoints AJAX para cada passo do wizard. |
| `wp_ajax_nopriv_xkhm_import_step` | (se necessário) para acesso não autenticado — **não**; wizard só admin. |
| `wp_cli` | Registar comando `wp xkhm import`. |

### 1.3 Constantes de Configuração (definidas em `wp-config.php`)

```php
define('XKHM_DB_HOST', 'localhost');
define('XKHM_DB_NAME', 'linuxkonect');
define('XKHM_DB_USER', 'linuxkonect');
define('XKHM_DB_PASSWORD', 'segredo-forte');
define('XKHM_DB_PREFIX', 'tbl'); // prefixo WHMCS
define('XKHM_FLUTTER_BASE', plugin_dir_url(__FILE__) . 'assets/flutter/');
```

## 2. Wizard de Importação (Multi-step AJAX)

| Step | UI | Acção (server) | Validação |
|------|----|----------------|-----------|
| 1 – Origem | Radio: *Ficheiro .sql* **ou** *MySQL remoto* (host, user, pass, db) | Armazenar opção em transient `xkhm_import_source`. | Se remoto: testar conexão (`mysqli_connect`). |
| 2 – Esquema | Lista das 94 tabelas esperadas (checkbox all). | `SHOW TABLES` na origem; comparar com `WHMCS_53_TABLES`. | Falha se tabelas críticas ausentes (`tblclients`, `tblinvoices`, `tblhosting`, `tbldomains`, `tblconfiguration`, `tbladmins`). |
| 3 – Importação | Barra de progresso (AJAX long-poll ou Server-Sent Events). | `ImportService::run($source, $tables)` – chunked `INSERT … SELECT` / `LOAD DATA INFILE` (se ficheiro). Transação por tabela; `COMMIT` a cada 5k linhas. | Contagem de linhas importadas vs origem; erros gravados em `xkhm_import_log`. |
| 4 – Relatório | Tabela resumo: tabela, linhas origem, linhas importadas, avisos, erros. Botão "Finalizar" limpa transients. | — | — |

### 2.1 Serviço de Importação (`src/Service/ImportService.php`)

- Métodos públicos:
  - `testConnection(array $params): bool`
  - `validateSchema(mysqli $src): array` → retorna `[missing => [], extra => []]`
  - `import(array $source, array $tables): Generator` – *yields* progresso (`['table'=>..., 'done'=>N, 'total'=>M]`).
- Usa `mysqli` com `MYSQLI_USE_RESULT` para streaming.
- Desactiva FK checks temporariamente (`SET FOREIGN_KEY_CHECKS=0`).
- Reutiliza `SchemaMap` para normalizar nomes de colunas se necessário (embora esquema seja idêntico).

## 3. Shortcode `[xkhm_client_area]`

```php
function xkhm_client_area_shortcode() {
    $base = XKHM_FLUTTER_BASE;
    return <<<HTML
<div id="xkhm-app" data-base="{$base}"></div>
<script src="{$base}flutter_bootstrap.js" defer></script>
HTML;
}
add_shortcode('xkhm_client_area', 'xkhm_client_area_shortcode');
```

- O `flutter_bootstrap.js` espera `window.xkhmBase` ou `data-base` para definir `<base href>`.
- O plugin enfileira o script apenas quando shortcode presente (`wp_enqueue_script` no `the_content` filter).

## 4. Rotas API (parse_request)

```php
add_filter('request', function($vars) {
    if (isset($vars['pagename']) && str_starts_with($vars['pagename'], 'api/v1/')) {
        // Despachar para ApiV1Controller via container PSR-7 (Slim) instanciado uma vez.
        return ['xkhm_api_v1' => $vars['pagename']];
    }
    if (isset($vars['pagename']) && $vars['pagename'] === 'api.php') {
        return ['xkhm_api_legacy' => true];
    }
    return $vars;
});
add_action('template_redirect', function() {
    if (get_query_var('xkhm_api_v1')) {
        Xkhm\Bootstrap::dispatchApiV1(get_query_var('xkhm_api_v1'));
        exit;
    }
    if (get_query_var('xkhm_api_legacy')) {
        Xkhm\Bootstrap::dispatchApiLegacy();
        exit;
    }
});
```

- `Xkhm\Bootstrap` cria `ApiV1Controller` / `ApiController` reutilizando os mesmos serviços (DI container simples).

## 5. Comando WP-CLI `wp xkhm import`

```php
WP_CLI::add_command('xkhm import', function($args, $assoc) {
    $source = $assoc['source'] ?? 'file'; // file|remote
    $file   = $assoc['file'] ?? '';
    $host   = $assoc['host'] ?? '';
    // ... usa ImportService::run() e imprime progresso via WP_CLI::log()
});
```

## 6. Testes (aes-verify)

| Camada | Ficheiro | O que testa |
|--------|----------|-------------|
| PHPUnit | `tests/Integration/WordPressPluginTest.php` | Bootstrap WP, activar plugin, verificar shortcode output, rewrite rules, admin menu existência. |
| PHPUnit | `tests/Integration/ImportServiceTest.php` | `testConnection`, `validateSchema`, `import` (dump pequeno de fixture). |
| Playwright | `tests/Playwright/test_wordpress_plugin.py` | Sobe container WP + plugin, executa wizard com dump de exemplo, verifica cliente na área Flutter. |
| Static | `make lint` | PHPStan level 5 no código novo (`includes/`, `src/Service/ImportService.php`). |

## 7. Riscos & Mitigações

| Risco | Prob. | Impacto | Mitigação |
|-------|-------|---------|-----------|
| Dump > 300 MB → timeout PHP | Alta | Falha importação | Chunked inserts, `set_time_limit(0)`, AJAX progressivo, opção CLI. |
| Conflito prefixo tabelas (`wp_` vs `tbl`) | Média | Dados errados | Constante `XKHM_DB_PREFIX`; prefixo fixo `tbl`. |
| Segurança credenciais remotas | Alta | Vazamento | Credenciais só em memória (transient encriptado com `wp_create_nonce`), nunca gravadas em DB/arquivo. |
| Conflito de rewrite com outros plugins | Baixa | 404 nas APIs | Prioridade alta (`add_rewrite_rule(..., 'top')`); testar com permalinks padrão. |
| Flutter assets não servidos (caminho errado) | Baixa | Área cliente branca | `XKHM_FLUTTER_BASE` configurável; fallback para `plugin_dir_url`. |

## 8. Critérios de Aceitação (repetidos do ticket)

- [ ] Estrutura de plugin gerada em `wordpress-plugin/`.
- [ ] Shortcode `[xkhm_client_area]` carrega Flutter.
- [ ] Rotas `/api/v1/*` e `/api.php` respondem (JSON) sem sessão WP.
- [ ] Wizard de importação funcional (4 passos) + comando WP-CLI.
- [ ] Testes PHPUnit + Playwright verdes.
- [ ] Documentação `wordpress-plugin/README.md`.

## 9. Próximos Passos (aes-build)

1. Criar esqueleto `wordpress-plugin/` com `composer.json` (`"require": { "xkhm/core": "@dev" }` via path repository).
2. Implementar `includes/bootstrap.php`, `shortcodes.php`, `rewrite.php`, `admin-menu.php`.
3. Criar `src/Service/ImportService.php` com métodos acima.
4. Wizard AJAX (`import-wizard.php`) + JS (Vanilla/Alpine) na página admin.
5. Comando WP-CLI.
6. Copiar build Flutter (`make flutter-build`) para `assets/flutter/` no plugin (script de build `make wp-plugin-assets`).
7. Escrever testes (PHPUnit + Playwright).
8. Rodar `make test && make lint && make tokens-check && make wp-plugin-test`.

---

*Documento gerado na fase **plan** do AES. Após aprovação, avançar para **build**.*
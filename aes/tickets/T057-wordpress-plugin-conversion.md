---
ticket: T057
title: Conversão da aplicação para plugin WordPress com importação de base de dados WHMCS
sprint: sprint-01
priority: high
status: pending
created: 2026-10-24
---

# T057 — Conversão para plugin WordPress + importação WHMCS

## Contexto
O objetivo é empacotar o **xkhm** como um plugin WordPress que possa ser instalado em qualquer site WP. O plugin deve:

1. **Substituir a área de cliente WHMCS** pela interface Flutter (já existente em `public/app/`) servida pelo plugin.
2. **Expor a API v1** (`/api/v1/*`) e a API legada WHMCS (`/api.php`) para que plugins como *WHMCS Bridge* continuem a funcionar.
3. **Incluir um assistente de importação** que, dado um dump SQL ou credenciais de um WHMCS 5.3.x, importe clientes, produtos, domínios, faturas, tickets, transações e configurações para as mesmas tabelas (`linuxkonect`) usadas pelo xkhm.
4. **Não alterar o esquema da base de dados** — as 94 tabelas WHMCS 5.3.9 permanecem intactas.

## Critérios de Aceitação
- [ ] Estrutura de plugin WordPress (`xkhm.php`, `includes/`, `assets/`, `admin/`) gerada em `wordpress-plugin/`.
- [ ] `xkhm.php` regista:
    - *shortcode* `[xkhm_client_area]` que carrega o bootstrap Flutter (`/app/index.html`).
    - *rewrite rules* para `/api/v1/*` e `/api.php` apontarem para os controladores PHP existentes.
    - *admin menu* “xkhm → Importar WHMCS” com página de assistente.
- [ ] Assistente de importação:
    - Passo 1: escolha de origem (ficheiro `.sql` ou credenciais MySQL remotas).
    - Passo 2: validação de esquema (94 tabelas, versões 5.3.9).
    - Passo 3: importação por lotes (chunked inserts, transações, barra de progresso via AJAX).
    - Passo 4: relatório final (registos importados, avisos, erros).
- [ ] O plugin usa as **mesmas classes de serviço/repositório** do xkhm (autoload via Composer) — zero duplicação de lógica.
- [ ] Testes:
    - PHPUnit: `tests/Integration/WordPressPluginTest.php` (bootstrap WP, activa plugin, verifica shortcode e endpoints).
    - Playwright: `test_wordpress_plugin.py` (instala WP + plugin em container, faz importação de dump de exemplo, verifica cliente na área de cliente).
- [ ] Documentação `wordpress-plugin/README.md` com instruções de instalação, configuração de `wp-config.php` (constantes `XKHM_DB_*`), e como rodar a importação.

## Escopo
**Inclui:**
- Estrutura de plugin WordPress.
- Integração do bootstrap Flutter (`public/app/`) como asset do plugin.
- Rotas WP REST / admin-ajax que despacham para `ApiV1Controller` e `ApiController`.
- Página de admin “Importar WHMCS” com wizard multi-step (AJAX).
- Script CLI `wp xkhm import` (WP-CLI) para importação não-interactiva.
- Reutilização total do código existente em `src/` (composer autoload).

**Exclui:**
- Alterações ao esquema da base de dados.
- Nova UI de admin WHMCS (mantém painel PHP actual).
- Faturação certificada (T041).

## Dependências
- T034 (painel admin base) — done
- T035 (serviços por tipo) — done
- T046 (cron / avisos) — done
- T056 (detalhe cliente tabs) — in progress

## Riscos Conhecidos
- Conflito de nomes de tabelas se o WP já tiver prefixo `wp_` — o plugin deve usar o mesmo prefixo que o WHMCS original (`tbl`).
- Tamanho do dump (centenas de MB) — importação deve ser streaming/chunked.
- Compatibilidade PHP 8.4 + WP 6.5+.
- Segurança: credenciais de importação não ficam gravadas; apenas em memória durante o wizard.

## Próximos Passos (AES)
1. **aes-plan** — especificação técnica detalhada (arquitetura do plugin, hooks WP, fluxo de importação, mapa de tabelas).
2. **aes-build** — implementar estrutura, shortcode, rotas, wizard, comando WP-CLI.
3. **aes-verify** — PHPUnit + Playwright + `make wp-plugin-test`.
4. **aes-review** — revisão hostil (segurança, performance, UX do wizard).
5. **aes-learn** — shadow docs sobre padrões WP + importação massiva.
---
project: xkhm
created: 2026-10-20
current_sprint: sprint-01
current_ticket: T024
---

# XKHM — WHMCS Reimplementation

**Goal:** Reverse-engineer the live WHMCS 5.3.x service at `xk.linuxkafe.com` using REA and runtime evidence, then reimplement it as a new, clean service (xkhm) that is fully compatible with the same MySQL database (`linuxkonect`) and the same `configuration.php` settings.

**Origin:** `/home/seyon/dev/whmcs/` — the ionCube stripping/reconstruction project (T001–T048). That project proved the reconstruction path is viable but left 38/735 files reconstructed with some PHP 8.4 lint errors and the rest still encrypted.

## Strategy

xkhm is a **fresh reimplementation**, not a continuation of the reconstruction. It will:
1. Use REA + runtime observation to understand what WHMCS actually does
2. Reimplement only the features present on the live service
3. Connect to the same MySQL database (`linuxkonect`) with the same credentials
4. Be written in a modern language/framework (to be decided in T001)

## Tickets

| ID | Title | Priority | Status |
|----|-------|----------|--------|
| T001 | REA analysis of live service + project scaffold | high | done |
| T002 | Database schema survey + configuration audit | high | done |
| T003 | Feature inventory: client area, billing, domains, tickets, affiliates | high | done |
| T004 | Language/framework selection + architecture decision | high | done |
| T005 | Core domain model implementation | medium | done |
| T006 | Client authentication & session management | medium | done |
| T007 | Billing & invoicing engine | medium | done |
| T008 | Domain management (checker, registrar modules) | medium | done |
| T009 | Support ticketing system | medium | done |
| T010 | Knowledgebase module | low | done |
| T011 | Affiliates module | low | done |
| T012 | Admin panel (core CRUD) | medium | partial — só a autenticação (ver T034) |
| T013 | Payment gateway integrations | medium | done |
| T014 | SSL configuration module | low | done |
| T015 | Server status / network issues | low | done |
| T016 | API layer (WHMCS API compatibility) | medium | done |
| T017 | Docker deployment + production hardening | medium | done |
| T018 | Migration validation: same DB, same data, same config | high | done |
| T019 | Feature parity gap analysis | medium | done |
| T020 | Performance & load testing | low | pending |
| T021 | Playwright E2E test harness (PHP backend) | high | done |
| T022 | Flutter client application (web + mobile) | high | done |
| T023 | Dark mode automático + compatibilidade mobile | high | done |
| T024 | Interruptor manual de tema (system / light / dark) | medium | pending |
| T025 | Migração completa para Flutter (retirar o front-end PHP) | high | done |
| T026 | Importação dos dados de produção + diagnóstico | standard | done |
| T027 | Exposição do MySQL: incidente e correcção | standard | done |
| T028 | Alinhar com o esquema WHMCS 5.3.9 real + formatos de senha | high | done |
| T029 | Rever a exposição dos restantes contentores (ollama, searxng) | medium | pending |
| T030 | Repor a senha do administrador `linuxkafe` na base de produção | medium | pending |
| T031 | Sistema de recuperação de credenciais (cliente + admin) | high | done |
| T032 | Rodar a senha do MySQL + entrega de correio pelo Postfix local | high | done |
| T033 | O e-mail da recuperação leva um link (e a página que faltava) | high | done |
| T034 | O painel de administração (as funções que faltavam) | high | done |
| T035 | Os serviços do cliente, pelo seu tipo | high | done |

> **Note on numbering.** T020 was originally reserved for "Performance & load
> testing". The Flutter conversion was planned as **T022** and the Playwright
> harness as **T021** (the harness had to exist first). The tickets were
> renumbered at plan time; T020 keeps its original meaning. The
> front-end replacement decision moved from T023 to **T025** when T023 was
> taken by the dark-mode and mobile work.

## Sprint 01 — status

| Ticket | Title | Status | Verification |
|--------|-------|--------|--------------|
| T021 | Playwright E2E harness | done | 141 E2E tests green; 14 production defects fixed |
| T022 | Flutter client application | done | 15 Flutter E2E + 40 widget tests green; design tokens single-sourced |
| T023 | Dark mode + compatibilidade mobile | done | 70 new assertions; 15 review defects fixed |
| T025 | Migração completa para Flutter | done | 7 ecrãs novos; as 13 rotas HTML respondem 302 para a app |
| T026 | Importação dos dados de produção | done | dump de 372 MB importado; `make db-check`/`db-import` testados |
| T027 | Exposição do MySQL | done | porta no loopback, credenciais locais próprias |
| T028 | Esquema WHMCS 5.3.9 real + formatos de senha | done | 89 PHPUnit + 141 Playwright contra a base **real** |
| T031 | Recuperação de credenciais | done | 103 PHPUnit + 152 Playwright; fluxo completo de cliente e admin, **sem alterar o esquema** |
| T032 | Rotação do MySQL + correio | done | senhas fora do repositório, healthcheck que autentica, tokens entregues por correio |
| T033 | Link de recuperação | done | o e-mail leva um link; o token viaja no fragmento e **não** chega ao servidor |
| T034 | O painel de administração | done | 9 áreas, com pesquisa, filtros, paginação e **escritas**; 128 PHPUnit + 189 Playwright |
| T035 | Os serviços pelo seu tipo | done | o tipo vem do produto, não de uma lista escrita à mão; ecrã novo na app |

### Open decisions

- **T024** — the manual theme toggle. The operator asked for *automatic*
  dark mode, so the system preference is the only wired behaviour. The
  palette structure already supports "system / light / dark"; what is
  missing is the storage and the precedence rules.
- **T029** — `ollama` (11434) and `searxng` (8081) are also published on
  `0.0.0.0`. They are not part of xkhm, but they share the same host and
  the same exposure that caused T027. They should be reviewed.
- **T030** — the production password of `linuxkafe` is only recoverable
  by the WHMCS itself. The new recovery flow (T031) gives the *operator*
  a way in; it does not touch the production database.

### ⚠ Outstanding action for the operator

The **production database password was compromised**: it was in
`configuration.php` and in the git history, and it was the password of a
MySQL instance published on the public internet (T027).

Since T032:

- it is **out of every versioned file** — the repository now holds the
  *shape* of the configuration (`${XKHM_DB_PASSWORD:?...}`), never a
  value;
- the **local** database has its own rotated, strong credentials, in an
  untracked `.env` (mode 0600);
- the tool that rotates the production password is written and tested:
  `python3 tools/rotate_db_password.py --host 10.0.3.1 --user linuxkonect --apply`.

What is **left**: the production server (`10.0.3.1`) is not reachable
from this machine, so the rotation itself has to be run where it is.
Rewriting the git history — which would remove the old value from the
commits — is a destructive operation on every clone, and is the
operator's call.

### The rule that T034 brought

> **A ticket without a `TXXX-verify.md` is not `done`.**

The verification is the phase that catches "the acceptance criteria
were not met" — and T012 slipped through without one, and the kanban
carried `done` for a panel that did not exist. The kanban now cites
the verification file for every ticket that has one.

### Test gates (all green)

```
make test            # 128 PHPUnit tests — contra a base real
make lint            # PHPStan level 5, 0 errors
make e2e             # 195 Playwright tests (API + Flutter) — dados reais
make flutter-test    # 45 Flutter unit + widget tests
make flutter-e2e     # 15 Playwright tests (Flutter web build)
make tokens-check    # tokens in sync, no hard-coded colours (Dart and CSS)
make db-check        # diagnosis of the production database
make db-test-accounts  # contas de desenvolvimento
make db-reset-tokens   # tokens de recuperação à espera de entrega
make db-reset-client   # repõe a senha de um cliente
make db-reset-admin    # repõe a senha do administrador
make db-env            # cria o .env local com senhas fortes (não versionado)
make db-rotate         # roda a senha da base, verificando o resultado
make db-mail-send      # entrega os tokens de recuperação por correio
make db-mail-send --dry  # mostra o que seria entregue (sem enviar)
```

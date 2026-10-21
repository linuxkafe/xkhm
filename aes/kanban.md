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
| T012 | Admin panel (core CRUD) | medium | done |
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

### Open decisions

- **T024** — the manual theme toggle. The operator asked for *automatic*
  dark mode, so the system preference is the only wired behaviour. The
  palette structure already supports "system / light / dark"; what is
  missing is the storage and the precedence rules.
- **T029** — `ollama` (11434) and `searxng` (8081) are also published on
  `0.0.0.0`. They are not part of xkhm, but they share the same host and
  the same exposure that caused T027. They should be reviewed.

### ⚠ Outstanding action for the operator

The **production database password is compromised**. It is in
`configuration.php` and in the git history, and it was the password of a
MySQL instance published on the public internet (T027). It must be
rotated, and the local database must never use it again.

### Test gates (all green)

```
make test            # 89 PHPUnit tests — contra a base real
make lint            # PHPStan level 5, 0 errors
make e2e             # 141 Playwright tests (API + Flutter) — dados reais
make flutter-test    # 40 Flutter unit + widget tests
make flutter-e2e     # 15 Playwright tests (Flutter web build)
make tokens-check    # tokens in sync, no hard-coded colours (Dart and CSS)
make db-check        # diagnosis of the production database
make db-test-accounts  # contas de desenvolvimento
```

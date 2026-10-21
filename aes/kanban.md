---
project: xkhm
created: 2026-10-20
current_sprint: sprint-01
current_ticket: T022
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
| T023 | Decide PHP front-end replacement vs. fallback (needs production traffic data) | medium | pending |

> **Note on numbering.** T020 was originally reserved for "Performance & load
> testing". The Flutter conversion was planned as **T022** and the Playwright
> harness as **T021** (the harness had to exist first). The tickets were
> renumbered at plan time; T020 keeps its original meaning.

## Sprint 01 — status

| Ticket | Title | Status | Verification |
|--------|-------|--------|--------------|
| T021 | Playwright E2E harness | done | 132 E2E tests green; 14 production defects fixed |
| T022 | Flutter client application | done | 9 Flutter E2E + 11 widget tests green; design tokens single-sourced |

### Open decisions

- **T023** — the PHP front-end is still live and fully tested. Whether to
  retire it in favour of the Flutter application needs production traffic
  data. The Flutter app is already served at `/app/` and is the primary
  client UI.

### Test gates (all green)

```
make test          # 85 PHPUnit tests
make lint          # PHPStan level 5, 0 errors
make e2e           # 132 Playwright tests (PHP backend)
make flutter-test  # 11 Flutter unit + widget tests
make flutter-e2e   # 9 Playwright tests (Flutter web build)
make tokens-check  # design tokens in sync, no hard-coded colours
```

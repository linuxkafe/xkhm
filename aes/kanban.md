---
project: xkhm
created: 2026-10-20
current_sprint: sprint-01
current_ticket: T007
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
| T007 | Billing & invoicing engine | medium | pending |
| T008 | Domain management (checker, registrar modules) | medium | pending |
| T009 | Support ticketing system | medium | pending |
| T010 | Knowledgebase module | low | pending |
| T011 | Affiliates module | low | pending |
| T012 | Admin panel (core CRUD) | medium | pending |
| T013 | Payment gateway integrations | medium | pending |
| T014 | SSL configuration module | low | pending |
| T015 | Server status / network issues | low | pending |
| T016 | API layer (WHMCS API compatibility) | medium | pending |
| T017 | Docker deployment + production hardening | medium | pending |
| T018 | Migration validation: same DB, same data, same config | high | pending |
| T019 | Feature parity gap analysis | medium | pending |
| T020 | Performance & load testing | low | pending |

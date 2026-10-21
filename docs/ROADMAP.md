# ROADMAP — XKHM

## Backlog

### Sprint 01 — Foundation & Analysis
| ID | Feature | Impact | Effort | Priority | Status |
|----|---------|--------|--------|----------|--------|
| T001 | REA analysis of live service + project scaffold | high | medium | high | in-progress |
| T002 | Database schema survey + configuration audit | high | medium | high | pending |
| T003 | Feature inventory: client area, billing, domains, tickets, affiliates | high | high | high | pending |
| T004 | Language/framework selection + architecture decision | high | low | high | pending |

### Sprint 02 — Core Implementation
| ID | Feature | Impact | Effort | Priority | Status |
|----|---------|--------|--------|----------|--------|
| T005 | Core domain model implementation | high | high | high | pending |
| T006 | Client authentication & session management | high | medium | high | pending |
| T007 | Billing & invoicing engine | high | high | high | pending |
| T008 | Domain management (checker, registrar modules) | high | high | medium | pending |

### Sprint 03 — Supporting Modules
| ID | Feature | Impact | Effort | Priority | Status |
|----|---------|--------|--------|----------|--------|
| T009 | Support ticketing system | high | high | high | pending |
| T010 | Knowledgebase module | medium | medium | low | pending |
| T011 | Affiliates module | medium | medium | low | pending |
| T012 | Admin panel (core CRUD) | high | high | medium | pending |

### Sprint 04 — Integration & Deployment
| ID | Feature | Impact | Effort | Priority | Status |
|----|---------|--------|--------|----------|--------|
| T013 | Payment gateway integrations | high | high | medium | pending |
| T014 | SSL configuration module | medium | low | low | pending |
| T015 | Server status / network issues | low | low | low | pending |
| T016 | API layer (WHMCS API compatibility) | high | high | medium | pending |
| T017 | Docker deployment + production hardening | high | medium | medium | pending |
| T018 | Migration validation: same DB, same data, same config | high | medium | high | pending |
| T019 | Feature parity gap analysis | medium | medium | medium | pending |
| T020 | Performance & load testing | medium | medium | low | pending |

## Notes

- T001 is in progress — this ticket.
- The live service is in Portuguese (Brazilian); all UI strings should match.
- The `configuration.php` credentials are:
  - DB host: `10.0.3.1`
  - DB name: `linuxkonect`
  - DB user: `linuxkonect`
  - DB pass: `<a senha de produção — rodada; ver aes/tickets/T032.md>`
  - CC hash: `<o hash de encriptação do WHMCS — partilhado, no ambiente>`

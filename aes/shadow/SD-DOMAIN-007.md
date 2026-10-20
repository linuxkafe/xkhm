# SD-DOMAIN-007 — WHMCS ticket lifecycle: open → answered → in progress → closed

- **Insight**: WHMCS ticket lifecycle: (1) Open → Aberto (new ticket), (2) Answered → Respondido (staff replied, awaiting client), (3) In Progress → Em Andamento (actively working), (4) Closed → Fechado (resolved). Tickets have departments (Geral, Técnico, Financeiro, Vendas) and priorities (Baixa, Média, Alta, Urgente).
- **Impact**: TicketService implements the full lifecycle. TicketController provides client-facing ticket management with Portuguese UI.
- **Applied To**: T009 support tickets. Future admin ticket management (T019) will extend this service.
- **Date**: 2026-10-20
---
ticket: T056
title: Detalhe do cliente: tabs, billing, domínio editável, search/pagination
sprint: sprint-01
priority: high
status: in-progress
created: 2026-10-23
---

# T056 — Detalhe do cliente: tabs, billing, domínio editável, search/pagination

## Context
O detalhe do cliente (`admin/clients.php?id=N`) atualmente apresenta todas as secções sequencialmente, o que torna a página longa e difícil de navegar. O operador precisa de:
- Ver o volume de faturação total e do ano corrente
- Editar domínios diretamente a partir do detalhe do cliente
- Navegar por tabs para separar as secções (serviços, domínios, faturas, etc.)
- Ocultar secções vazias
- Search/pagination nas secções com mais de 5 entradas

## Acceptance Criteria
- [ ] Header do cliente mostra: total faturado (histórico) + faturado este ano
- [ ] Secções organizadas em tabs: Serviços (primeiro), Domínios, Faturas, Pedidos, Tickets, Transações
- [ ] Secções sem dados são ocultas (não renderizam)
- [ ] Tabs com >5 entradas têm search bar + paginação
- [ ] Domínios: cada linha tem botão "Editar" que abre modal/rota de edição
- [ ] Serviços permanece a primeira tab (a mais importante)
- [ ] Layout responsivo (mobile: tabs viram accordion ou scroll horizontal)
- [ ] Playwright tests cobrem: tabs, billing display, domain edit link, search/pagination

## Scope
**In scope:**
- ClientController::detail() — restructure with tabs
- AdminPanelRepositoryPDO — add methods for billing totals and paginated domain/invoice lists
- AdminLayout — tab component CSS/JS
- DomainController — edit from client context
- Playwright tests

**Out of scope:**
- Nova rota de API (usa rotas existentes)
- Alteração de esquema de BD
- Faturação certificada (T041)

## Dependencies
- T034 (painel admin base) — done
- T035 (serviços por tipo) — done
- T055 (edição de domínio) — done

## Known Risks
- Performance: queries de agregação (SUM) em tblinvoices/tblaccounts
- UX: tabs em mobile precisam de design cuidadoso
- Consistência: search/pagination deve seguir padrão existente no painel

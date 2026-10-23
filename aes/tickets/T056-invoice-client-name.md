---
ticket: T056
title: Invoices: mostrar nome do cliente e pesquisa abrangente
sprint: sprint-01
priority: high
status: in-progress
created: 2026-10-23
---

# T056 — Invoices: mostrar nome do cliente e pesquisa abrangente

## Context
A lista de faturas (`admin/invoices.php`) atualmente mostra apenas o ID do cliente (`#123`) sem o nome. A pesquisa só funciona pelo número da fatura (`invoicenum`). O operador precisa:
- Ver o nome do cliente na lista
- Pesquisar por nome do cliente, email, empresa, além do número da fatura

## Acceptance Criteria
- [ ] Lista de faturas mostra: "Nome do Cliente" (link) em vez de apenas "#ID"
- [ ] Pesquisa abrange: número da fatura, nome do cliente, email, empresa
- [ ] Mantém performance (índices, LIMIT)
- [ ] Playwright tests cobrem: client name display, search by client name/email

## Scope
**In scope:**
- AdminPanelRepositoryPDO::listInvoices() — JOIN com tblclients
- AdminPanelRepositoryPDO::countInvoices() — WHERE expandido
- InvoiceController::index() — render client name
- AdminPanelRepositoryInterface — assinaturas atualizadas
- Playwright tests

**Out of scope:**
- Alteração de esquema BD
- Faturação certificada (T041)

## Dependencies
- T034 (painel admin base) — done

## Known Risks
- Performance: JOIN em tabelas grandes (tblinvoices + tblclients)
- Consistência: search deve seguir padrão existente

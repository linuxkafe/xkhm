---
ticket: T050
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T050-plan.md
---

# T050 — Verify: Associar um serviço a um cliente

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **208 tests, 717 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | A página do cliente tem a accão «Associar um serviço» | `GET /admin/clients.php?id=84` mostra o botão | ✅ |
| AC2 | O serviço é criado com o produto, o ciclo, o valor, a renovação e o domínio | o teste manual — o serviço aparece com 25,00 €, Anual, 2027-10-22, `cliente-servico.example.com` | ✅ |
| AC3 | O serviço aparece na lista, com o valor e a renovação | a lista mostra `Valor` e `Próxima renovação` | ✅ |
| AC4 | O operador ajusta o valor e a renovação na página do serviço | T040 — `/admin/services.php?id=N` | ✅ |
| AC5 | Todos os portões verdes | tabela acima | ✅ |

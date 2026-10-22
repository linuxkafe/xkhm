---
ticket: T045
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T045-plan.md
---

# T045 — Verify: O cliente pode encomendar um serviço novo

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **208 tests, 720 assertions** — green (8 da encomenda) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **251 testes** — green (4 do fluxo da encomenda) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | A app mostra o **catálogo** | `testTheCatalogueHasTheProductsAndTheirPrices` — 22 produtos, com o tipo, o grupo e o preço de cada ciclo; e `test_the_catalogue_is_public` — é público, como a `order.php` do WHMCS | ✅ |
| AC2 | O cliente escolhe o produto, o ciclo e o domínio, e **encomenda** | `testTheClientOrdersAService` (Playwright) — a interface real; e `testAnOrderCreatesTheOrderAndTheInvoice` — o pedido nasce `Pending`, a fatura `Unpaid` | ✅ |
| AC3 | O pedido aparece nos **Pedidos** e a fatura nas **Faturas** | `testTheOrderAppearsInTheOrdersAndTheInvoices` (Playwright) | ✅ |
| AC4 | Ao pagar, o **serviço** é criado e aparece nos **Serviços** | `testPayingTheInvoiceCreatesTheService` — o serviço nasce com o domínio, o ciclo, o preço pago e a renovação a um ano; e `test_the_operator_marks_the_invoice_as_paid` (Playwright) | ✅ |
| AC5 | O serviço herda o **preço** que o cliente pagou | `testPayingTheInvoiceCreatesTheService` — 25,00 € (o da fatura), e não o do produto | ✅ |
| AC6 | Todos os portões verdes | tabela acima | ✅ |

## O caminho, verificado

```
produto 1 (LINUXKONECT XK2, 25,00 €/ano, alojamento web)
  │
  ├─ POST /api/v1/client/orders {productId: 1, cycle: "Annually", domain: "..."}
  │    ├─ tblorders     #100 · Pending · 25,00 € · invoiceid
  │    └─ tblinvoices   #384 · Unpaid · 25,00 € · vence a +7 dias
  │         └─ tblinvoiceitems · type='Product' · relid=1 (o **produto**)
  │
  └─ POST /admin/invoices.php {id: 384}   (o operador marca como paga)
       ├─ tblinvoices   #384 · Paid
       └─ tblhosting    #108 · Active · 25,00 € · Annually · renova a +1 ano
            └─ tblorders #100 · Active
```

## O item da fatura aponta para o produto; o da renovação, para o serviço

É a convenção do WHMCS, e o ciclo de faturação já a respeita:

| Item | `type` | `relid` |
|------|--------|---------|
| Encomenda nova | `Product` | o **produto** |
| Renovação (T046) | `Hosting` | o **serviço** |

E o `relid` da renovação aponta para o serviço — que é o que permite
ao ciclo saber que um serviço já foi facturado para um período.

## Os cinco defeitos que os testes apanharam

1. **`Annually` não é `annually`** — o preço vinha `-1.00` e a
   encomenda era recusada.
2. **A data de pagamento** — a data zero é recusada pelo
   `NO_ZERO_DATE` (o modo foi alinhado com o da produção).
3. **O `orderId` do serviço** — a provisionação não encontrava o
   pedido.
4. **Pagar duas vezes** — criava dois serviços (o guard é o
   `orderid`).
5. **O teste sujava a base** — os testes seguintes contavam
   serviços a mais.

## A base fica como estava

A limpeza é uma parte do teste, e é verificada:

```
as faturas nao pagas: 287  Unpaid     (a real, importada)
os servicos de teste:   0
os pedidos de teste:    0
```

---
ticket: T050
phase: plan
status: done
created: 2026-10-22
tier: standard
verdict: pass
requires:
  - aes/tickets/T050.md
produces:
  - aes/tickets/T050-plan.md
blocked_by: ''
---

# T050 — Plan: Associar um serviço a um cliente

## Two-Agent Analysis (standard tier)

### Critic Phase

```
ASSUMPTIONS I'M MAKING:
- [KNOWN]    O cliente já pode encomendar (T045), e o serviço nasce
             quando a fatura é paga.
- [KNOWN]    O ajuste do preço e da renovação existe (T040, no
             `ServiceController`).
- [INFERRED] A lacuna é o **associar directo** — um serviço que o
             operador quer criar sem passar pela encomenda (um
             serviço antigo, um ajuste de contas, um domínio que já
             estava cá fora).

WHAT WASN'T SPECIFIED:
- Se o serviço associado cria uma fatura. A decisão: **não**. O
  WHMCS também não o faz por omissão, e a fatura é um passo à
  parte (T045). O operador associa o serviço e, se quiser, fatura
  depois.

ALTERNATIVES I DIDN'T CHOOSE:
- **O mesmo formulário da encomenda.** Rejeitado: a encomenda
  cria a fatura, e aqui não há fatura. São duas coisas, e o
  operador distingue-as.

RISKS & SIDE EFFECTS:
- O serviço criado tem de ter o **preço** e a **renovação** que o
  operador escolheu — e não os do produto (que podem não ser os
  certos para este cliente).

COST OF BEING WRONG: LOW — é uma acção, e o teste apanha o erro.
```

## A solução

1. `src/Http/Controller/Admin/ClientController.php` — a acção
   `addservice`, com o formulário e a criação.
2. A lista dos serviços mostra o valor e a renovação (já o fazia,
   T040).
3. O botão «Associar um serviço» na página do cliente.

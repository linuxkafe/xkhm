---
ticket: T051
phase: plan
status: done
created: 2026-10-22
tier: standard
verdict: pass
requires:
  - aes/tickets/T051.md
produces:
  - aes/tickets/T051-plan.md
blocked_by: ''
---

# T051 — Plan: O login no telefone, e os restantes campos

## Two-Agent Analysis (standard tier)

### Critic Phase

```
ASSUMPTIONS I'M MAKING:
- [KNOWN]    O defeito é a wrapper de `Semantics` à volta do campo.
             A causa: o nó de semântica é recriado a cada tecla, e o
             foco do teclado suave do Android web perde-se.
- [INFERRED] A wrapper é a causa: os campos do `order_screen` e do
             `domain_checker_screen`, que não tinham a wrapper, não
             tinham o defeito.
- [ASSUMED]  A `key` é suficiente para os testes. Verificado: os
             testes usam `get_by_label` e `get_by_role`, não o
             identificador de semântica.

WHAT WASN'T SPECIFIED:
- Que tipo de teclado cada campo deve ter. A decisão: a
  `TextInputType` do Material, com o `textInputAction` que corresponde
  ao fluxo (next/done/search).

ALTERNATIVES I DIDN'T CHOOSE:
- **Manter a wrapper, mas com `explicitChildNodes: false`.**
  Rejeitado: a wrapper existe para o teste, e o teste não precisa dela
  (usa `get_by_label`). Removê-la é mais simples, e remove o
  defeito.
- **Desligar a semântica por completo.** Rejeitado: a acessibilidade
  perde-se. A chave basta.

RISKS & SIDE EFFECTS:
- Os testes do Playwright usam `get_by_label('Email')` — a
  `labelText` continua a existir, e a chave também. Os testes passam
  (verificado: 17/17 no flutter_app, 251 no total).

COST OF BEING WRONG: LOW — é um formulário, e os testes apanham a
regressão.
```

## A solução

1. Remover a wrapper de `Semantics` dos três campos do login.
2. Adicionar o `textInputAction`, o `keyboardType`, o `autocorrect` e
   o `enableSuggestions` a todos os campos do formulário.
3. O mesmo tratamento nos restantes ecrãs (o domínio, o código, a
   encomenda).

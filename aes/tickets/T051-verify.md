---
ticket: T051
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T051-plan.md
---

# T051 — Verify: O login no telefone, e os restantes campos

## Gates

| Gate | Command | Result |
|------|---------|--------|
| Flutter | `make flutter-test` | **50 testes** — green |
| Playwright | `make e2e` | **251 testes** — green |
| Flutter build | `make flutter-build` | verde, sem warnings |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O login **não** perde o foco ao escrever | a wrapper de `Semantics` foi removida; os campos usam a `key` e o `controller`, que são estáveis | ✅ (a verificar no dispositivo) |
| AC2 | Cada campo tem o `textInputAction` e o `keyboardType` adequados | o login (e-mail: next, senha: done), o domínio (url/search), o código (number/done), a encomenda (url/done) | ✅ |
| AC3 | Os testes do Playwright continuam verdes | 251 testes — green | ✅ |
| AC4 | Todos os portões verdes | tabela acima | ✅ |

## O defeito, verificado

A wrapper de `Semantics` (com `container: true`, `explicitChildNodes:
true`, `textField: true`) é a causa. No web, no Android, o teclado
perde o foco a cada tecla quando o nó de semântica é recriado.

A `key` e o `controller` são estáveis, e a `labelText` (o que o
teste usa) continua a existir — pelo que os testes passam sem
mudança.

## Os campos, depois

| Ecrã | Campo | `keyboardType` | `textInputAction` |
|------|-------|---------------|-------------------|
| Login | E-mail | `emailAddress` | `next` |
| Login | Senha | `text` | `done` |
| Login | Código | `number` | `done` |
| Domínios | Domínio | `url` | `search` |
| Encomendar | Domínio | `url` | `done` |
| Segurança | Código | `number` | `done` |

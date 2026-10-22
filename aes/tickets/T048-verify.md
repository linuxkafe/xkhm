---
ticket: T048
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T048-plan.md
---

# T048 — Verify: O tipo de interligação do servidor

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **208 tests, 717 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Playwright | `make e2e` | **251 testes** — green (3 novos do tipo) |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O formulário indica o **tipo** e explica-o | `test_the_server_form_asks_for_the_module` — o campo `select[name="type"]`, a explicação, e o «módulo que aprovisiona» | ✅ |
| AC2 | A lista traz os módulos do WHMCS, incluindo os dois em uso | o mesmo teste: `virtualmin`, `whmcs_multisite` e `cpanel` estão no menu; e os dois servidores da instalação mostram o seu | ✅ |
| AC3 | O tipo é **gravado** e lido de volta | `test_the_server_keeps_its_module` — a coluna `Módulo` da lista passa a mostrar `cPanel` | ✅ |
| AC4 | Um servidor novo sem tipo é **recusado** | `test_a_server_without_a_module_is_refused` — «O servidor precisa do tipo de interligação» | ✅ |
| AC5 | Todos os portões verdes | tabela acima | ✅ |

## O defeito que o teste apanhou

O `CatalogueRepositoryPDO::saveServer` **não** gravava a coluna
`type`. Um servidor novo ficava com o tipo vazio — e sem tipo, o
aprovisionamento não existe. O teste `test_a_server_without_a_module_is_refused`
garante agora que o campo é obrigatório, e `test_the_server_keeps_its_module`
que o valor sobrevive.

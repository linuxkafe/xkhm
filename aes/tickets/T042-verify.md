---
ticket: T042
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T042-plan.md
---

# T042 — Verify: A configuração em secções, com os nomes reais

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **185 tests, 550 assertions** — green (9 novos das etiquetas) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **243 testes** — green (5 novos da configuração) |
| Tokens | `make tokens-check` | em sincronia, sem cores à mão |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | As secções são as **páginas do WHMCS** | `testTheSettingsAreSplitIntoSections` — `general`, `servers`, `domains`, `invoices`, `tax`, `currencies`; a lista vem de `tools/extract_whmcs_labels.py`, que lê as páginas `config<name>.php` do WHMCS | ✅ |
| AC2 | Cada definição mostra o **nome real** | `testTheLabelComesFromTheLanguageFile` — `LateFeeType` → «Tipo da Multa por Atraso», `CompanyName` → «Nome da Empresa»; e `test_the_settings_have_their_real_names` (Playwright) | ✅ |
| AC3 | A secção maior não engole as restantes | `test_the_sections_are_not_one_bucket` — 19 secções, a maior com 76 de 235 | ✅ |
| AC4 | A secção é auditável | `SECTION_PREFIXES` e `SECTION_ALIASES` são constantes escritas à mão; `testTheSettingsLandInTheSectionOfItsKind` fixa o destino de cada família | ✅ |
| AC5 | A configuração continua editável, e a escrita vale | `test_a_setting_can_be_changed_and_is_kept` — o valor muda, é lido de volta da base, e é reposto | ✅ |
| AC6 | Todos os portões verdes | tabela acima | ✅ |

## As secções, verificadas

O que o painel mostra, e o que cada secção tem:

```
Geral 76 · Diversos 33 · Impostos 9 · Clientes 9 · Modelos de e-mail 11
Automação 11 · Faturas 14 · Domínios 10 · Tickets 8 · Perguntas de
segurança 11 · Moedas 3 · Servidores 5 · Portas de pagamento 1
Afiliados 7 · Promoções 4 · Módulos adicionais 5 · Campos
personalizados 2 · ...
```

O `LateFeeType` vai para «Geral» (é onde o ficheiro de idioma o
põe), e não para «Impostos» — o que é uma diferença em relação à
intuição, e está **correcta**: no WHMCS, a multa por atraso é uma
definição da página geral, e o IVA é o que está na página de
impostos. O ficheiro de idioma sabe; a intuição não.

## O ficheiro de etiqueta, verificado

- O `tools/extract_whmcs_labels.py` gera `resources/whmcs_admin_labels.php`
  (2 372 etiquetas, 38 secções, com a **origem** de cada etiqueta).
- O ficheiro é copiado pelo Dockerfile nos **dois** estágios — o de
  build e o de execução. Sem isso, as secções caíam todas em
  «Diversos» (175 de 235) e nada o denunciava.

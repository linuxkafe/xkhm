---
ticket: T040
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T040-plan.md
---

# T040 — Verify: O preço e o prazo de renovação

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **168 tests, 429 assertions** — green (9 novos do prorrateio) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **225 testes** — green (5 novos) |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O administrador muda o preço e o prazo | `test_the_change_is_applied_and_recorded` — 24,99 € → 15,00 € | ✅ |
| AC2 | Mudar de ciclo traz o preço desse ciclo | `testTheQuoteBringsThePriceOfTheNewCycle`; e `test_the_cycles_offered_come_from_the_product` — só entram os ciclos com preço no `tblpricing` | ✅ |
| AC3 | O prorrateio é calculado e mostrado **antes** | `test_the_quote_shows_the_proration_before_anything_changes` — «A diferença é a favor da empresa: 2,99 € serão facturados na próxima renovação» | ✅ |
| AC4 | Uma diferença a favor do cliente vira crédito | `testADifferenceInTheCustomersFavourBecomesCredit` — o crédito do cliente cresce com a diferença | ✅ |
| AC5 | A mudança fica registada nas notas do cliente | `testTheChangeIsAppliedAndRecorded` — a nota em `tblnotes` com o preço antigo, o novo e o prorrateio | ✅ |
| AC6 | A app mostra o preço e o prazo | `GET /api/v1/client/services` traz `amount`, `billingCycle`, `renewalDate`; o ecrã mostra-os | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## O prorrateio, verificado

O cálculo é a **diferença proporcional ao tempo que resta**:

```
Preço antigo 100,00 €, ciclo anual, faltam 182 dias
Preço novo   50,00 €
fração       182 / 365 = 0,498
diferença    (50,00 − 100,00) × 0,498 = −24,92 €   → crédito do cliente
```

E com o período quase no fim, o prorrateio é zero — não há o que
devolver:

```
faltam 1 dia de 365  →  fração 0,003  →  diferença −0,14 €
```

E com o período **inteiro** por passar (a renovação daqui a 365 dias),
a diferença é a total — o cliente pagou o ano, e o preço novo é o do
ano.

## O que a aplicação **não** faz

- **Não recalcula o IVA.** A `tbltax` está vazia, e inventar uma taxa é
  pior do que mostrar o total que a fatura tem.
- **Não cria a fatura de renovação.** A antecedência é `30` dias
  (`CreateInvoiceDaysBefore`), e a criação da fatura é do motor de
  facturação.
- **Não troca o plano.** O `upgradepackages` do produto é trabalho de
  produto.

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | A nota tinha `\n` literais | a cadeia tinha escapes de shell | `PHP_EOL` |
| 2 | Os testes não eram isolados | o preço aplicado por um teste era visto pelo seguinte | cada teste lê o preço actual e usa um **diferente**; e a semente repõe o preço e o crédito do cliente de teste |
| 3 | A asserção do prorrateio estava invertida | o preço actual é 100 e o novo é 50 — estava ao contrário | corrigida |

O segundo é o que mais importa: **um teste que muda um estado
partilhado é um teste que muda o mundo**. A semente passou a ser
dona do estado da conta de desenvolvimento, e a repõe.

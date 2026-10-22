---
ticket: T038
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T038-plan.md
---

# T038 — Verify: O aviso de pagamento

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **168 tests, 429 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **225 testes** — green (220 + 5) |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O PDF de um aviso abre e tem o conteúdo | `test_the_notice_is_a_pdf` — `%PDF`, 27 KB; e o texto extraído mostra emissor, destinatário, itens, totais e vencimento | ✅ |
| AC2 | O PDF diz explicitamente que **não** é um documento fiscal | o texto do PDF: «Este documento é um aviso de pagamento. Não é uma fatura, nem um documento fiscal, e não serve de título para efeitos fiscais.» | ✅ |
| AC3 | O PDF só é servido ao dono (ou a um administrador) | `test_another_clients_invoice_is_refused` (404), `test_the_notice_needs_a_session` (401), `test_the_administrator_gets_the_notice_too` (200) | ✅ |
| AC4 | O aviso pode ser **reenviado** por correio, com o PDF anexado | `test_the_notice_can_be_resent` (a fila) e a entrega verificada: o anexo vai em base64, 27 687 bytes, e descodifica para `%PDF-1.7` | ✅ |
| AC5 | O reenvio **não** duplica nem altera a fatura | `test_the_resend_does_not_change_the_invoice` — número, total, estado, datas e vencimento | ✅ |
| AC6 | A app e o painel dizem «aviso de pagamento» | o painel (lista e detalhe), o PDF, o e-mail e a app («Aviso de pagamento #…») | ✅ |
| AC7 | A fila do correio aceita um anexo | as linhas `subject:` e `attachment:` na fila, lidas pelo correio do host | ✅ |
| AC8 | Todos os portões verdes | tabela acima | ✅ |

## O documento, como sai

Uma página, com:

```
LINUXKAFE
Rua Dom João de França 1037 1, 4420-110 Gondomar
mail@linuxkafe.com
NIF:                                     ← vazio, e o painel diz porquê

Aviso de pagamento
9001
Emitido em 22/08/2026        Vence em 05/09/2026        Pago

DESTINATÁRIO
Xkhm Teste · xkhm-test@example.com

Descrição                              Valor
Alojamento Web Partilhado - 1 ano      24,99 €

Subtotal 24,99 €   Imposto 0,00 €   Crédito 0,00 €   Total 24,99 €

COMO PAGAR
MB Way  918342670

Este documento é um aviso de pagamento. Não é uma fatura, nem um
documento fiscal, e não serve de título para efeitos fiscais.
A fatura certificada é emitida separadamente, pelo programa de
faturação certificado.
```

## O que a análise encontrou (e que está por resolver)

A **configuração** é o que faltava, e é do T039. O que o documento
mostra hoje:

| | De onde vem | Estado |
|---|------------|--------|
| O nome | `CompanyName` | `LINUXKAFE` |
| A morada | `RegistrarAdminAddress1` (o `CompanyAddress1` está vazio) | preenchida |
| A cidade, o código postal, o telefone | `RegistrarAdmin*` | preenchidos |
| O email | `RegistrarAdminEmailAddress` | preenchido |
| **O NIF** | `CompanyTaxNumber` | **vazio** — e o painel diz que falta |
| **Os dados bancários** | `CompanyBankDetails` | **vazios** |
| **A moeda** | `MainCurrency` | **vazia** |

O documento **diz** o que não sabe, em vez de inventar. É o que a
aplicação faz, e é o que o painel reporta.

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | O anexo **não** chegava no e-mail | a fila carregava um caminho **dentro do contentor** (`/var/www/html/...`) e o correio corre no **host** | o caminho passou a ser **relativo** à raiz do armazenamento, e cada lado o resolve contra a sua |
| 2 | O anexo era acrescentado **depois** do envio | a mensagem era enviada duas vezes | o anexo é acrescentado **antes**, e é enviada uma vez |
| 3 | 500 na rota do PDF | o atributo `route` é nulo no Slim 4 | os argumentos da rota chegam como **array**, à terceira do método |
| 4 | 500 na construção dos serviços | os serviços eram criados **antes** das suas dependências | a ordem do arranque pela ordem das dependências |
| 5 | `?id=abc` devolvia 500 | o `id` não era validado | validado, e o que não é é um **404** |

O primeiro é o mais instructive: **os dois lados do mesmo sistema não
partilham os caminhos absolutos**. A aplicação vive no contentor e o
correio no host; a fila é a fronteira, e a fronteira tem de ser
expressa de uma forma que os dois lados entendam.

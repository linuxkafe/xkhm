---
ticket: T039
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T039-plan.md
---

# T039 — Verify: A configuração

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **168 tests** — green (8 novos das definições) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Playwright | `make e2e` | **225 testes** — green |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | As definições vêm da `tblconfiguration` | `testTheSettingsComeFromTheTable` — 235 definições, `CompanyName = LINUXKAFE` | ✅ |
| AC2 | O painel mostra-as **agrupadas** | Empresa · Registo do cliente · IVA · Moeda · Facturação · Numeração · Correio · Pagamento · Outras | ✅ |
| AC3 | O painel **edita** as definições | `CompanyTaxNumber`, `MainCurrency` etc. editados e lidos de volta; `InstanceID` e `License` **intocados** | ✅ |
| AC4 | O painel diz **o que falta** | `testTheMissingReportSaysWhatIsMissing` — e a página lista o NIF, os dados bancários, a informação de pagamento e a moeda | ✅ |
| AC5 | A numeração sequencial funciona | 1, 2, 3, 4 — sem repetições, e o contador fica com **uma** linha |
| AC6 | A edição não quebra o resto | a lista explícita das definições editáveis; `InstanceID`, `License` e `token_namespace` estão de fora | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## O que a análise encontrou

| Definição | Valor | O que falta |
|-----------|-------|-------------|
| `CompanyName` | `LINUXKAFE` | — |
| `RegistrarAdminAddress1` | `Rua Dom João de França 1037 1` | está noutro prefixo |
| `RegistrarAdminCity` | `Gondomar` | idem |
| `RegistrarAdminPostalCode` | `4420-110` | idem |
| `RegistrarAdminPhone` | `918342670` | idem |
| `RegistrarAdminEmailAddress` | `mail@linuxkafe.com` | idem |
| `CompanyAddress1`…`CompanyCountry` | **vazias** | a empresa não tem morada no prefixo que o WHMCS usa |
| `CompanyTaxNumber` / `CompanyVatNumber` | **vazias** | **não há NIF em lado nenhum** |
| `CompanyBankDetails` | **vazia** | não há dados bancários |
| `CompanyPaymentInfo` | **vazia** | não há informação de pagamento |
| `MainCurrency` | **vazia** | a moeda |
| `TaxEnabled` | **vazia** | o IVA não está ligado |
| `SequentialInvoiceNumbering` | **vazia** | a numeração sequencial está **desligada** |
| `SequentialInvoiceNumberFormat` | `{NUMBER}` | o formato |
| `SequentialInvoiceNumberValue` | `1` | o contador |
| `CreateInvoiceDaysBefore` | `30` | os dias de antecedência da renovação |

A **resolução** da empresa: o que se procura em `Company*` e, quando
está vazio, vai ao `RegistrarAdmin*` — que é de onde vem, nesta
instalação.

## O painel

As definições estão **agrupadas**, e cada grupo tem um formulário. As
que não estão na lista explícita das editáveis são **de leitura** — o
`InstanceID`, o `License` e o `token_namespace` ficam de fora, e a
exclusão é auditável.

E no topo há o **relatório do que falta**:

> **O que falta para um documento de pagamento completo**
> - `CompanyTaxNumber` — obrigatório num documento fiscal — sem NIF não há fatura
> - `CompanyBankDetails` — os dados bancários, para o cliente pagar por transferência
> - `CompanyPaymentInfo` — a informação de pagamento (MB Way, referência, …)
> - `MainCurrency` — a moeda do documento

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | A escrita **duplicava** as definições | a tabela **não tem chave única** (`setting` é um `text` com um índice não-único), por isso o `ON DUPLICATE KEY` nunca dispara — e a base importada já tinha duplicadas | a escrita apaga e escreve: fica **exactamente uma** linha |
| 2 | A numeração devolvia sempre `1` | o `SELECT` lia uma das linhas duplicadas | o contador passou a ser lido **com a linha bloqueada** (`FOR UPDATE`), e escrito por apagar-e-inserir |
| 3 | O `ON DUPLICATE KEY` dava a ilusão de funcionar | sem chave única, ele **nunca** dispara — e a falha é silenciosa | o `UPDATE` + `SELECT` de existência, em vez do atalho que não fazia nada |

O primeiro merecia um aviso: um `ON DUPLICATE KEY UPDATE` numa tabela
**sem chave única** é um `INSERT` disfarçado. A aplicação do WHMCS faz
`UPDATE` e, só se não houver linha, `INSERT` — e a razão está agora
escrita no código.

---
id: SD-DOMAIN-013
title: "O registo de um domínio é uma instalação — e cobra-se a taxa"
tier: domain
epistemic_state: verified
confidence: 0.9
date: 2026-10-22
source: T044
tags: [whmcs, domains, pricing, tblpricing, comparison]
---

# O registo de um domínio é uma instalação — e cobra-se a taxa

## O que aconteceu

O preçário de domínios devia vir do `tblpricing`, e devolvia **0**
para todos os TLD. Duas causas, que se mascaravam:

1. `getTLDPrices()` lia `tbldomainpricing` e tirava o preço de
   `msetupprice` — uma coluna que **essa tabela não tem**. Ela tem o
   **registador** (`eppcode`) e as opções de cada TLD. A camada de
   compatibilidade sintetizava a coluna em 0.
2. O preço está em `tblpricing`, e na coluna `msetupfee` — **não** em
   `annually`. Porque o registo de um domínio **é** uma instalação:
   cobra-se a taxa de instalação.

O resultado era o mesmo zero por dois caminhos diferentes, e o
sintoma era indistinguível dos dois.

## A regra

> **Quando um valor não faz sentido, a primeira pergunta é «que coluna
> é esta?» — e a segunda é «o que é esta coisa, na prática?».**

A segunda pergunta é a que resolve: um registo de domínio é um
*setup*, e o preço de um *setup* está na coluna do *setup*. A
estrutura (`tblpricing` tem uma coluna por ciclo **e** uma
`msetupfee`) está certa — o que faltava era saber que o registo cai
na segunda.

E o porto seguro não é o raciocínio:

> **O original é o oráculo.** Os números (18 / 18 / 35) vieram de ver
> o `domainchecker.php` do WHMCS vivo com o Playwright, e de
> comparar. Um `18` plausível seria aceite com a mesma confiança que
> um `0`.

## Os três preços

O preçário do WHMCS tem três preços por TLD, e cada um é uma linha
do `tblpricing` com o seu `type`:

| Preço | `tblpricing.type` |
|-------|-------------------|
| Registar | `domainregister` |
| Transferir | `domaintransfer` |
| Renovar | `domainrenew` |

E o TLD vem do `tbldomainpricing`, por `tblpricing.relid`.

## Ver

- `src/Repository/DomainRepositoryPDO.php` — o `getTLDPrices()`
- `src/Service/DomainService.php` — `getTLDPriceTable()`
- `aes/tickets/T044-verify.md` — a comparação com o WHMCS vivo

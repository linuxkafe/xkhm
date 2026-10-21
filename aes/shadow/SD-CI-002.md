---
id: SD-CI-002
title: "Playwright descobre bugs de compatibilidade com a base de dados que nenhum teste unitário vê"
tier: ci
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T021
tags: [playwright, e2e, database, whmcs, schema]
---

# Playwright descobre bugs de compatibilidade com a base de dados

## O padrão

Quando se reimplementa um sistema contra uma base de dados **existente** e
**estranha** (o WHMCS `linuxkonect`), as colunas que o código assume e as
colunas que a tabela tem não coincidem. A class PHP devolve `null` em
silêncio, o PHP 8.4 transforma isso em `TypeError` num `htmlspecialchars()`,
e a página morre.

Bugos encontrados **apenas** pelo E2E:

| Código assumia | A tabela tem |
|----------------|--------------|
| `tbldomains.name` | `tbldomains.domain` |
| `tbldomains.regdate` | `tbldomains.registrationdate` |
| `tbldomains.registrationperiod` | `tbldomains.period` |
| `tblticketreplies.ticketid` | `tblticketreplies.tid` |

Nenhum teste unitário apanha isto: um mock devolve sempre a chave que o
código espera.

## A lição

**Um teste de contrato contra a base de dados real é o que garante a
compatibilidade.** O `SchemaParityTest` executa cada `SELECT` do repositório
contra o esquema e afirma que cada coluna referenciada existe. Passou a correr
na *fase de build* e apanha a deriva no momento em que é introduzida.

```php
$this->assertGreaterThan(
    0,
    (int) $exists->fetchColumn(),
    sprintf('Column %s.%s does not exist in the WHMCS schema', $table, $column)
);
```

## O segundo padrão: as páginas autenticadas são invisíveis

Um rastejo anónimo nunca chega a `/ssl.php`, `/domain.php` ou
`/affiliates.php`. Um `use` em falta numa rota autenticada fica **sem
qualquer sinal** até alguém com sessão real abrir a página. Foi
exatamente o que aconteceu: `AffiliateController` usava `Layout::render()`
sem importar a classe — a página rebentava e nada no CI dizia nada.

A correção foi um módulo E2E dedicado às rotas autenticadas
(`test_client_pages.py`), que faz login a sério e visita cada uma.

## O terceiro padrão: o charset do cliente

O `init.sql` era carregado pelo cliente `mysql` com o charset por omissão
(latin1). Todo o conteúdo pt-BR — "Fatura de domínio", "Dúvida sobre a
fatura" — ficava com as acentuações destruídas. Nenhum teste falhava: os
dados existiam, apenas desafinavam.

A correcção é uma linha no topo do script:

```sql
SET NAMES utf8mb4;
```

## Regra de ouro

**Quando o requisito é "compatível com a base de dados actual", a base de
dados tem de estar no circuito de testes.** Um mock esconde precisamente a
classe de bug que o requisito proíbe.

## Ver

- `tests/Integration/SchemaParityTest.php`
- `tests/Playwright/test_client_pages.py`
- `docker/init.sql`

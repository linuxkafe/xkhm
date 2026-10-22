---
id: SD-CI-005
title: "O `INSERT` neutro lê-se do esquema — e há duas camadas de defaults"
tier: ci
epistemic_state: verified
confidence: 0.95
date: 2026-10-22
source: T043
tags: [schema, database, whmcs, insert, defaults, mysql]
---

# O `INSERT` neutro lê-se do esquema — e há duas camadas de defaults

## O que aconteceu

O painel passou a gerir o catálogo ([[T043]]), e a primeira tentativa
de criar um produto deu:

```
ERROR 1364 (HY000): Field 'description' doesn't have a default value
```

E o servidor:

```
ERROR 1364 (HY000): Field 'assignedips' doesn't have a default value
```

A causa não é um erro: é o esquema. O WHMCS declara quase todas as
colunas como `NOT NULL` **sem default**, e o MySQL em modo estrito
recusa o `INSERT` parcial:

```
tblproducts    70 colunas NOT NULL sem default  (46 texto, 13 int, 3 decimal, 1 varchar)
tblservers     24 colunas NOT NULL sem default  (21 texto, 3 int)
tblpricing     todas as colunas NOT NULL sem default, e `type` é uma enumeração
```

O `SchemaMap::REQUIRED_DEFAULTS` (ver [[SD-CI-004]]) resolve isto
**com uma lista escrita à mão** por tabela — e resolve bem, porque
esses valores são **semânticos** (o `type` de um domínio é `Register`,
a `date` de uma encomenda é agora). Uma lista escrita à mão para 70
colunas, porém, apodrece ao primeiro `ALTER TABLE`.

## A regra

> **Os defaults que não têm significado leem-se do esquema. Os que
> têm, escrevem-se à mão.**

São **duas camadas**, e a confusão entre elas é o erro:

| Camada | Onde | Como | Quando o valor importa |
|--------|------|------|------------------------|
| **Semântica** | `SchemaMap::REQUIRED_DEFAULTS` | escrita à mão | sim — `type` = `Register`, `date` = hoje |
| **Neutra** | `SchemaDefaults::neutral()` | lida do `information_schema` | não — só o `INSERT` é aceito |

A camada neutra lê do esquema, e exclui de propósito as colunas cujo
valor neutro seria uma **mentira** — as enumerações e as datas. Uma
coluna dessas que falte tem de falhar **em voz alta**, e não ser
preenchida com um valor vazio que significa outra coisa.

## Os dois detalhes que custaram um erro cada

- **`order` é uma palavra reservada do MySQL.** Uma coluna do
  `tblproducts`. Um `INSERT` que a liste sem crases é um erro de
  sintaxe, e o erro aparece longe da causa. Todos os identificadores
  dinâmicos vão entre crases.
- **`tblhosting` não tem `productid`.** Tem **`packageid`**. A
  inferência pelo nome ("deve ser `productid`") é uma hipótese — e
  custou um 500.

## Ver

- `src/Repository/SchemaDefaults.php` — a camada neutra
- `src/Repository/SchemaMap.php` — a camada semântica
- `src/Repository/CatalogueRepositoryPDO.php` — quem usa as duas
- `tests/Integration/CatalogueRepositoryTest.php`

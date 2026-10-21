---
id: SD-CI-004
title: "Um fixture escrito à mão esconde o esquema real"
tier: ci
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T028
tags: [schema, database, whmcs, fixtures, testing]
---

# Um fixture escrito à mão esconde o esquema real

## O que aconteceu

O `docker/init.sql` do xkhm foi escrito à mão, a partir do conhecimento
do esquema WHMCS. Parecia correcto: 16 tabelas, nomes de coluna
plausíveis, a suíte de testes verde, a API a funcionar.

Quando o dump **real** foi importado (94 tabelas, 372 MB), o esquema
divergia em quase tudo:

| O código assumia | O WHMCS 5.3.9 tem |
|------------------|---------------------|
| `tblinvoices.datecreated` | `date` |
| `tbltickets.subject` | `title` |
| `tbltickets.priority` | `urgency` |
| `tblknowledgebase.content` | `article` |
| `tblknowledgebase.catid` | `parentid` |
| `tblknowledgebasecats.title` | `name` |
| `tblannouncements.message` | `announcement` |
| `tblorders.total` | `amount` |
| `tbldomains.period` | `registrationperiod` |
| `tbladmins.fullname` | `firstname` + `lastname` |
| `tbltickets.deptid` | (não existe) |
| `tblknowledgebase.description` | (não existe) |
| `tblaffiliatesclients` | `tblaffiliateshistory` |

E o que é pior: **os testes estavam verdes**. O fixture afirmava contra o
fixture. Um teste que valida o que o próprio teste construiu não valida
nada.

## A regra

**Um fixture escrito à mão não é uma fonte de verdade — é uma hipótese.**
A única fonte de verdade é o esquema real, e ele só aparece quando o dump
chega.

O que teria evitado as duas semanas de divergência: um `SchemaParityTest`
que **executasse os repositórios** contra a base real, em vez de validar
uma *cópia* do SQL. A versão que existia tinha a lista de `SELECT`s
escrita à mão no ficheiro de teste — exactamente o mesmo erro, noutro
sítio.

## A regra derivada

**Um teste que duplica o código está a testar a duplicação.** Se a
duplicação divergir, o teste continua verde e a aplicação quebra. É a
mesma razão pela qual o token pipeline é um *gerador* e não dois
ficheiros: a divergência tem de ser **detectável**, não evitada por
convenção.

## A terceira lição: os defaults

O WHMCS 5.3.9 declara quase todas as colunas como `NOT NULL` **sem
default**. Um `INSERT` que nomeie só as colunas interessantes é
rejeitado. A camada de mapeamento ganhou um bloco de defaults por tabela
— gerado a partir do esquema vivo, não escrito à mão.

## Ver

- `src/Repository/SchemaMap.php` — o mapeamento e os defaults
- `tests/Integration/SchemaParityTest.php` — executa os repositórios
- `aes/tickets/T028.md` — o plano

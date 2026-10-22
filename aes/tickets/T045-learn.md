---
ticket: T045
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T045-verify.md
---

# T045 — Learn: O cliente pode encomendar um serviço novo

## O que se aprendeu

### 1. O mesmo conceito tem dois nomes, e o código novo usou um só

O ciclo de faturação chama-se `Annually` no produto, no cliente e no
`tblhosting`. A coluna do `tblpricing` chama-se `annually`. O código
novo usou as duas interchangeavelmente — e o preço veio `-1.00`,
que o WHMCS usa para dizer «este produto não tem este ciclo».

**Aprendido:** o `-1.00` não é um preço: é um **código**. E a
primeira hipótese a levantar, quando um preço não faz sentido, é
«estou a ler a coluna certa?».

E a lição maior: **um valor de código que se parece com um valor
numérico é um perigo**. O `-1.00` podia ser um preço (um euro
negativo!) e ninguém notaria a diferença. Um código como
`UNAVAILABLE` não podia ser confundido.

### 2. O serviço nasce com o que o cliente **pagou**, não com o que está à lista

O `tblpricing` é o preço de lista. O `tblhosting.amount` é o que o
cliente paga. São diferentes sempre que há um desconto — e o erro
seria silencioso: o serviço apareceria com o preço de lista, e o
desconto desapareceria sem ninguém dar por isso.

**Aprendido:** aprovisionar não é «copiar o produto»: é «usar o
que o cliente pagou». E a lição **já tinha sido aprendida** no
prorrateio (T040) — o que mostra que uma lição que não é escrita
num sítio não está aprendida.

### 3. Um teste que muda os dados muda os **outros** testes

O teste da encomenda criava um serviço. Os testes dos serviços
contavam serviços. E passaram a falhar — não por um defeito, mas
porque o **ambiente** tinha mudado.

**Aprendido:** um teste que escreve tem de **limpar**, e a limpeza
é uma parte do teste (não uma tarefa de memória). A regra que
organiza tudo: *a suíte tem de deixar a base como a encontrou* —
e isso é verificável (a contagem no fim).

O mesmo se aplica ao operador: o teste marcou como paga uma fatura
**real** (a importada da produção) e a base ficou com o estado
alterado. A fatura foi reposta à mão — e o teste agora só toca nas
suas.

### 4. O `sql_mode` é parte do esquema (repetido, e confirmado)

A data zero é a convenção do WHMCS para «ainda não há data», e o
MySQL 8 recusa-a. A aplicação não conseguia escrever uma fatura
por pagar.

**Aprendido:** é a **segunda** vez que o `sql_mode` aparece como
causa (a primeira em T046, na mesma semana). Uma causa que aparece
duas vezes não é um acidente: é uma **propriedade** do ambiente de
que ninguém falou. E a propriedade de um dump importado não está
no `CREATE TABLE` — está nas **convenções** que o código que o
escreveu usava.

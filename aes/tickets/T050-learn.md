---
ticket: T050
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T050-verify.md
---

# T050 — Learn: Associar um serviço a um cliente

## O que se aprendeu

### 1. «Associar» e «encomendar» são duas coisas

A encomenda (T045) cria o pedido e a fatura, e o serviço nasce
quando a fatura é paga. Associar é outra coisa: o operador põe o
serviço na conta do cliente directamente, com o preço e a renovação
que ele escolheu — sem invoice, porque a fatura é um passo à parte.

**Aprendido:** a distinção importa, e o painel tem de a deixar
claro — um botão «Associar um serviço» (sem invoice) e um
«Encomendar» (com invoice). Confundir as duas é a forma de criar
faturas a mais, ou de não criar as que o cliente pediu.

### 2. O mesmo formulário, duas vezes

A página do cliente e a página do serviço precisam do **mesmo**
formulário (produto, ciclo, preço, renovação, domínio). A tentação
é escrevê-lo duas vezes; a regra é escrevê-lo uma vez e
referenciá-lo.

**Aprendido:** a duplicação de um formulário é a duplicação de um
bug. Se o campo do preço mudar num, tem de mudar no outro — e
ninguém se lembra.

### 3. O ajuste fino já existe (T040)

O operador ajusta o valor e a renovação na página do serviço
(`/admin/services.php?id=N`) — e o prorrateio é cotado antes de ser
aplicado. A página do cliente só **lista** e **associa**; o ajuste
vai para o sítio onde o cálculo é feito.

**Aprendido:** a mesma operação não deve existir em dois sítios.
A associação cria; o ajuste muda. Um único sítio para cada.

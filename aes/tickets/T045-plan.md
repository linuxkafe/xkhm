---
ticket: T045
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T045.md
produces:
  - aes/tickets/T045-plan.md
blocked_by: ''
---

# T045 — Plan: O cliente pode encomendar um serviço novo

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tinha um catalogo e uma nota. O que nao tinha era a
maquina: nao se podia comprar nada. Agora mete-se o produto no
carrinho, paga-se, e a coisa aparece na tua conta.

O ficheiro e' a maquina que liga as tres coisas: o carrinho, a nota
e o preco. Se uma das tres nao for a mesma coisa, o cliente paga
uma coisa e recebe outra.

## Como se fosse um especialista

[FAILURE MODE 1] — O ciclo do WHMCS e o do xkhm nao falam a mesma
lingua.

Mecanismo: o ciclo do WHMCS tem duas palavras: `Annually` (o ciclo,
como o cliente e o produto o escrevem) e `annually` (a coluna do
`tblpricing`). O codigo novo usou as duas como se fossem a mesma
coisa, e o preco veio `-1.00` — que e o «nao tem este ciclo».

Mitigation: um mapa explicito entre as duas (`CYCLE_COLUMNS`), e um
teste que encomenda com um ciclo e verifica o preco.

[FAILURE MODE 2] — O servico nasce com o preco do **produto**, e nao
com o que o cliente pagou.

Mecanismo: o `tblpricing` e o preco de lista. O servico pode ter um
desconto, e o desconto vive no `tblhosting.amount`. Se o servico
nascesse com o preco de lista, o desconto do cliente desaparecia —
e o mesmo erro ja tinha acontecido no prorrateio (T040).

Mitigation: o servico herda o **valor da fatura**, e o teste
verifica-o.

[FAILURE MODE 3] — A fatura por pagar e' criada sem a data de
pagamento, e o MySQL recusa.

Mecanismo: a coluna `datepaid` e' `NOT NULL`, e a convencao do
WHMCS para «ainda nao foi paga» e' a data zero. Com o `NO_ZERO_DATE`
do MySQL 8, essa data e' recusada — e o `INSERT` falha.

Mitigation: o `sql_mode` local foi alinhado com o da producao (ver
T046), e a data zero voltou a ser o valor neutro das datas.

[FAILURE MODE 4] — Encomendar duas vezes cria dois servicos.

Mecanismo: o operador marca a fatura como paga, e o servico nasce. Se
a accao for repetida — um duplo clique, uma repeticao da pagina — o
servico nasce **duas vezes**, e o cliente paga duas vezes.

Mitigation: antes de criar, o servico procura um que ja tenha o
`orderid` do pedido. E o teste paga **duas** vezes e afirma que há
**um** servico.

[FAILURE MODE 5] — O teste deixa dados na base.

Mecanismo: o teste da encomenda cria um pedido, uma fatura e um
servico. Sem limpeza, os testes seguintes contam servicos a mais — e
a contagem e' uma assercao. Foi o que aconteceu: os testes dos
servicos falharam depois do teste da encomenda.

Mitigation: a limpeza no fim do teste (por `docker exec`, como o
teste do aviso de pagamento ja fazia), e a verificacao de que a base
fica como estava.

[ASSUMPTION]
Se falso: se o `domainstatus` do servico novo devesse ser `Pending`
e nao `Active`.

O `Pending` e' o estado do WHMCS quando o servico espera por
aprovacao (a configuracao do produto). Nao ha aprovacao nesta
instalacao — o pagamento **aprova** — e o servico nasce `Active`. Se
a instalacao tiver produtos com aprovacao, este e' o sitio a
mudar.

[ALTERNATIVE FRAMING]
Em vez de uma pagina de encomenda, um **carrinho** (o WHMCS tem um
carrinho de varios artigos).

Rejeitado: o WHMCS 5.3.9 ja nao tem carrinho — a `order.php` e' um
formulario de um produto. E o pedido do operador e «associar novo
servico», nao «comprar varios».
```

## A solução

1. `src/Service/OrderService.php` — o caminho: o catálogo, o pedido,
   a fatura, e o serviço que nasce quando a fatura é paga.
2. `src/Service/MailPolicy.php` — a política dos avisos (T046).
3. `GET /api/v1/catalogue` (público) e `POST /api/v1/client/orders`.
4. `flutter/xkhm_app/lib/screens/order_screen.dart` — o formulário.
5. `POST /admin/invoices.php` — «marcar como paga», que é onde o
   serviço nasce (sem uma porta de pagamento, é o operador quem o
   diz).

## O que fica de fora

- **As opções de produto** (`configoption1..24`): o WHMCS mostra-as
  no formulário, e aqui ficariam por preencher.
- **O cupão de desconto** (`promocode`).
- **A propelledora** (o WHMCS provisiona no servidor, via módulo de
  automação — que depende de credenciais que não existem).
- **A loja** (o `order.php` público, com a escolha do produto pela
  porta da frente).

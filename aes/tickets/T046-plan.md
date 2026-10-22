---
ticket: T046
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T046.md
produces:
  - aes/tickets/T046-plan.md
blocked_by: ''
---

# T046 — Plan: O cron — e os avisos que não saem para os clientes

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tem um robot que passa de manha em manha. Ele olha as coisas
que estao para pagar, manda um carta a dizer "pague isto", e se
alguem nao pagar, fecha a loja.

O aviso e' como um bilhete de umariendade: diz o que se deve, ate
quando, e como pagar. A fatura verdadeira e' um documento do
Estado — e esse ainda nao existe aqui.

## Como se fosse um especialista

[FAILURE MODE 1] — O aviso sai para o cliente, e a instalação ainda
não está validada.

Mecanismo: o aviso de pagamento diz, na sua frente, que **não** é uma
fatura. Se ele for para o cliente, o cliente recebe um documento que
a empresa não pode emitir — e que, numa auditoria, vale contra a
empresa.

Mitigation: a restrição está em **dois** portões. A aplicação não
endereça o aviso ao cliente (vai para o operador, e o cliente viaja
com a mensagem); e o **correio do host** recusa qualquer aviso cujo
destinatário não esteja na lista, movendo-o para `failed/` com o
motivo. A lista vem do ambiente (`XKHM_MAILER_NOTICE_RECIPIENTS`),
que é a **única fonte** para os dois runtimes.

[FAILURE MODE 2] — A suspensão desliga o serviço de um cliente real.

Mecanismo: `AutoSuspension` está `on` na instalação, com 5 dias de
tolerância. Um serviço com uma fatura vencida é suspenso — e o
cliente fica sem serviço. Numa instalação não validada, isso é um
dano real e **irreversível** (o cliente não sabe que a culpa é do
sistema).

Mitigation: a suspensão está **desligada** por omissão
(`XKHM_CRON_ALLOW_SUSPENSION`). A *política* continua a ser a do
WHMCS (a definição `AutoSuspension`); o que a instalação controla é
se pode **agir** sobre ela. E o teste prova as duas coisas: sem a
permissão, o serviço fica `Active`; com ela, fica `Suspended`.

[FAILURE MODE 3] — O ciclo corre duas vezes e duplica a fatura.

Mecanismo: o ciclo é chamado por um crontab, e o crontab às vezes
corre duas vezes (um servidor lento, um awaken). Sem um guard, o
cliente recebe duas faturas da mesma renovação.

Mitigation: o guard está **nos dados**, não no código: o item da
fatura carrega o **vencimento do período**, e o ciclo só cria a
fatura se não houver um item com esse vencimento. E as datas do
serviço avançam **na mesma operação**. O teste corre o ciclo duas
vezes e afirma que há **uma** fatura.

[FAILURE MODE 4] — A `sql_mode` do MySQL é mais restrita que a da
produção, e a aplicação não consegue escrever o que o WHMCS escrevia.

Mecanismo: o `NO_ZERO_DATE` recusa a data zero — e a data zero é a
convenção do WHMCS para «ainda não há data» (a fatura por pagar, com
a data de pagamento vazia). Os **dados importados** têm isso: 7
faturas com `0000-00-00 00:00:00`. A aplicação local, com o modo do
MySQL 8, não conseguia escrever uma fatura por pagar.

Mitigation: o `sql_mode` local foi **alinhado com o da produção** (no
`docker-compose.yml`, com o motivo escrito). E a data zero voltou a
ser o valor neutro das datas.

[FAILURE MODE 5] — O `cron.php` do WHMCS é uma URL pública.

Mecanismo: o WHMCS corre o ciclo por HTTP, sem autenticação. Qualquer
pessoa que descubra o endereço dispara a facturação da instalação
inteira.

Mitigation: a rota exige o **token** (`XKHM_CRON_TOKEN`); sem ele,
recusa. E o ciclo corre, na prática, pela **linha de comando**, dentro
do contentor, onde a base de dados está.

[ASSUMPTION]
Se falso: se o `CreateInvoiceDaysBefore` (30 dias) não for a
política correcta, o ciclo fatura demasiado cedo.

É a definição do WHMCS, e é lida da configuração. O que o ciclo
**não** faz é decidir sozinho: lê a definição.

[ALTERNATIVE FRAMING]
Em vez de um cron, um botão no painel («correr o ciclo agora»).

Rejeitado como **única** via: o ciclo tem de correr sem alguém a
carregar num botão — foi esse o pedido. Mas a rota com token é
exatamente o botão, para quem quiser.
```

## A solução

1. `src/Service/CronService.php` — o ciclo, com as quatro acções.
2. `src/Service/MailPolicy.php` — quem pode receber os avisos.
3. `src/Repository/ServiceRepositoryPDO.php` — os serviços (o
   `tblhosting` não tinha repositório próprio).
4. `src/Repository/ActivityLogRepositoryPDO.php` — o registo, que é
   também o guard dos avisos.
5. `tools/cron.php` — a linha de comando, dentro do contentor.
6. `src/Http/Controller/CronController.php` — a rota, com token.
7. `docker/entrypoint.sh` e o `docker-compose.yml` — os directorios
   e o `sql_mode`.

## O que fica de fora

- **A terminação** dos serviços (`AutoTermination`, desligada nesta
  instalação).
- **A sincronização dos domínios** com os registadores — depende de
  credenciais que não existem.
- **A facturação de tickets** (o WHMCS factura tickets a partir de
  uma definição).
- **O Upgrade** automático.

---
ticket: T046
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T046-plan.md
---

# T046 — Verify: O cron — e os avisos que não saem para os clientes

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **192 tests, 577 assertions** — green (7 do ciclo, 8 da política) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Playwright | `make e2e` | **247 testes** — green (4 da política do correio) |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O ciclo gera a fatura de **renovação** | `testTheCycleRaisesTheRenewalInvoice` — a fatura nasce com o valor do **serviço** (42,00 €) e as datas avançam um ano | ✅ |
| AC2 | O ciclo aplica a **multa por atraso** | `testTheCycleAppliesTheLateFee` — 5,00 € de multa, o total da fatura sobe de 30,00 para 35,00, e a multa não é cobrada duas vezes | ✅ |
| AC3 | O ciclo **não** envia nada para os clientes | `testTheCycleQueuesTheNoticeToTheOperatorAndNotToTheClient` — a mensagem vai para `mail@linuxkafe.com`, e o cliente viaja com ela; e `testTheMailerRefusesANoticeForAClient` — o correio recusa um aviso para um cliente | ✅ |
| AC4 | O **correio** recusa um destinatário fora da lista | `test_the_mailer_refuses_a_notice_for_a_client` — nada é enviado, e o ficheiro vai para `failed/` com o motivo | ✅ |
| AC5 | O ciclo é **idempotente** | `testTheCycleIsIdempotent` — duas voltas, **uma** fatura; e `testTheNoticeIsSentOnlyOnce` — o segundo aviso não sai | ✅ |
| AC6 | O que o ciclo fez fica registado | `tblactivitylog` — «Fatura de renovação #N criada…», «Multa por atraso…», «Aviso de pagamento (upcoming) da fatura #N» | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## A restrição, verificada nos dois lados

| Portão | O que faz | Teste |
|--------|-----------|--------|
| A **aplicação** | O aviso nunca é endereçado ao cliente: vai para `mail@linuxkafe.com`, e o cliente viaja com a mensagem | `testTheCycleQueuesTheNoticeToTheOperatorAndNotToTheClient` |
| O **correio do host** | Um aviso cujo destinatário não esteja na lista vai para `failed/`, com o motivo — e **não sai** | `test_the_mailer_refuses_a_notice_for_a_client` |

A lista vem do ambiente (`XKHM_MAILER_NOTICE_RECIPIENTS`), que é a
única fonte para os dois runtimes. A recuperação de credenciais
**não** é restringida: nunca é automática — o operador pede-a, para um
cliente, de propósito.

## A suspensão

Desligada por omissão (`XKHM_CRON_ALLOW_SUSPENSION`):

```
sem a permissão:  o serviço continua 'Active'
com a permissão: o serviço passa a 'Suspended', e o registo diz porquê
```

A *política* continua a ser a do WHMCS (`AutoSuspension`, 5 dias); o
que a instalação controla é se pode **agir** sobre ela.

## O que o ciclo fez na instalação real

Nada — e é o resultado correcto:

```
faturas de renovacao: 0
multas por atraso:    0
avisos enfileirados:  0
servicos suspensos:   0
```

O serviço mais próximo da renovação vence a **2026-12-01**, e a
fatura é criada 30 dias antes. A instalação real não tem nada
vencido.

## Os quatro defeitos que os testes apanharam

1. **`sql_mode` mais restrito que a produção** — o `NO_ZERO_DATE`
   impedia escrever a data zero, que é a convenção do WHMCS para «não
   há data». O modo local foi alinhado com o da produção (com o
   motivo no `docker-compose.yml`).
2. **`docker/entrypoint.sh` com os caminhos errados** — criava
   `/var/www/html/reset-tokens` em vez de `storage/reset-tokens`, e
   não conhecia o `notice-pdfs`. O host não conseguia escrever lá, e
   a fila de avisos falhava **em silêncio**.
3. **Parametros misturados** no PDO (posicional e nomeado) — a
   suspensão rebentava com um erro de sintaxe do driver.
4. **O guard e o registo com textos diferentes** — o aviso saía duas
   vezes, porque o guard procurava uma frase e o registo escrevia
   outra.

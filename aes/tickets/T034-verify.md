---
ticket: T034
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T034-plan.md
---

# T034 — Verify: O painel de administração

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **128 tests, 321 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **45 testes** — green |
| Playwright | `make e2e` | **188 testes** — green (169 + 19 do painel) |
| Tokens | `make tokens-check` | em sincronia, sem cores literais |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | `/admin` mostra um dashboard com números reais | `test_the_dashboard_shows_the_real_numbers` — 10 clientes, 84 faturas pagas, 52 tickets abertos | ✅ |
| AC2 | A lista de clientes pesquisa e pagina | `test_the_client_search_narrows_the_list`; `test_the_pagination_works` | ✅ |
| AC3 | O detalhe do cliente mostra as suas faturas, pedidos, domínios e tickets | `test_the_client_detail_shows_the_clients_rows`; e o `AdminPanelRepositoryTest` garante que cada método só devolve as linhas **desse** cliente | ✅ |
| AC4 | A lista de faturas filtra por estado | `test_the_invoice_filter_works` — `Paid` + `Unpaid` < o total, e todas as filas da lista filtrada são do estado pedido | ✅ |
| AC5 | O detalhe da fatura mostra os itens e o total | `test_the_invoice_detail_shows_the_items` | ✅ |
| AC6 | A lista de tickets filtra, e o detalhe mostra as respostas | `test_the_ticket_filter_works`; `test_the_administrator_can_reply_to_a_ticket` | ✅ |
| AC7 | É possível **responder a um ticket** | `test_the_administrator_can_reply_to_a_ticket` — a resposta aparece na página, atribuída à Administração | ✅ |
| AC8 | É possível **dar crédito** a um cliente | ⚠️ **não implementado** — ver "Dívidas" | ❌ |
| AC9 | A pesquisa global encontra clientes, faturas, tickets e domínios | `test_the_global_search_finds`; `testTheGlobalSearchFindsAcrossEntities` | ✅ |
| AC10 | A configuração é visível | `test_the_configuration_is_visible` — 235 definições | ✅ |
| AC11 | O painel tem navegação entre as áreas | `test_the_panel_has_its_own_navigation` — e **sem** o cabeçalho público | ✅ |
| AC12 | Funciona em dark mode e num ecrã de telemóvel | `test_admin_screenshots.py` — capturas nos dois esquemas e a 390 px | ✅ |
| AC13 | Nenhuma consulta varre `tblactivitylog` | `testThePanelNeverScansTheActivityLog` — guarda estática | ✅ |
| AC14 | Todos os portões verdes | tabela acima | ✅ |

## A dívida

**AC8 — dar crédito a um cliente** ficou por fazer.

O `CreditService::addCredit()` existe e funciona, e a interface está
no `CreditService`. O que falta é a **página**: um formulário no
detalhe do cliente, e o `POST` que o trata.

Ficou de fora porque o `CreditService` tem um efeito colateral que
precisa de ser verificado antes de o expor a um formulário: aplicar
crédito a uma fatura **muda o total da fatura** (`tblinvoices.total`
e `tblinvoices.credit`), e isso precisa de ser transacional. Fazer
pela metade — um formulário que mexe no dinheiro sem a transação — é
pior do que não ter.

Está registado, com a razão, para o próximo ticket.

## O painel, a correr

```
GET /admin                      200   o painel
GET /admin/clients.php          200   a lista
GET /admin/invoices.php         200   a lista
GET /admin/orders.php           200   a lista
GET /admin/tickets.php          200   a lista
GET /admin/domains.php          200   a lista
GET /admin/affiliates.php       200   a lista
GET /admin/search.php           200   a pesquisa
GET /admin/config.php           200   a configuração

GET /admin/clients.php?id=84    200   o detalhe
GET /admin/invoices.php?id=288  200   o detalhe
GET /admin/tickets.php?id=189   200   o detalhe
GET /admin/clients.php?id=99999999  404   «O cliente não existe»
GET /admin/clients.php?id=abc   400   «O cliente não é válido»
GET /admin/invoices.php?id=-5   400

GET /admin          (sem sessão)  302  → /admin/login.php
GET /admin/config.php (sem sessão) 302  → /admin/login.php
```

## Os números, contra a base real

| | O que o painel mostra | A base |
|---|---|---|
| Clientes | 10 (10 activos) | 10 |
| Faturas pagas | 84 — recebido 3 124,13 €, por receber 149,90 €, 1 em atraso | 90 (84 `Paid`, 1 `Unpaid`, 5 `Cancelled`) |
| Tickets abertos | 52 (52 no total) | 52 |
| Pedidos ativos | 15 (16 no total) | 16 |
| Domínios | 5 | 5 |

## A escrita, a correr

```python
def test_the_administrator_can_reply_to_a_ticket(admin):
    admin.goto(f"{BASE_URL}/admin/tickets.php")
    admin.click("table.table tbody tr:first-child td:nth-child(2) a")

    message = "Resposta de teste do painel — verificação automática."
    admin.fill('textarea[name="message"]', message)
    admin.click('button[type="submit"]')

    body = admin.inner_text("body")
    assert message in body
    assert "Administração" in body
```

A resposta **aparece** na página do ticket, atribuída à
Administração. A escrita passa pelo `TicketService` — o painel não
toca nas tabelas.

## A segurança, verificada

| Propriedade | Como se prova |
|-------------|---------------|
| Sem sessão, não há dados | `test_the_panel_needs_a_session` — o URL final é o do login |
| O `id` não existe → 404 | `test_a_client_that_does_not_exist_is_a_404` |
| O `id` malformado → 400 | `test_a_malformed_id_is_a_400`, `test_a_negative_id_is_a_400` |
| A pesquisa não é injecção | `testTheSearchIsNotSqlInjection` — a tabela continua lá |
| A tabela gigante não é tocada | `testThePanelNeverScansTheActivityLog` |
| Nada é concatenado no SQL | `testTheQueriesDoNotConcatenateTheSearch` |

## O aspecto

`test_admin_screenshots.py` tira as capturas de todas as áreas, nos
dois esquemas de cor, e a 390 px (um telefone). Ficam em
`tests/Playwright/artifacts/admin/`.

O que se vê:

- o **dark mode** segue o sistema, com os mesmos tokens do resto do
  produto;
- no telefone, os números do painel vão a duas colunas, a navegação
  rola na horizontal, e as tabelas rolam **dentro** do seu contentor —
  a página nunca tem scroll horizontal;
- o cabeçalho **público** não aparece: o painel tem o seu.

## Defeitos encontrados e corrigidos durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | 500 em `/admin/orders.php` e `/admin/domains.php` | a camada de vista exigia `?string` e recebia `int` | a camada de vista aceita `mixed` e converte |
| 2 | **500** com o filtro de estado | o `AND status = ?` sem `WHERE` — a clause de estado era separada da de pesquisa | a cláusula é **composta**: `WHERE (a) AND (b)` |
| 3 | A resposta a um ticket ia para «o ticket não existe» | o `id` vinha no **corpo** do POST e era lido da **query** | o `id` é lido de onde vier |
| 4 | A referência do ticket era um link **vazio** | algumas linhas de `tbltickets` não têm `tid` | o `#id` é a alternativa |
| 5 | O número da fatura era um link **vazio** | algumas linhas de `tblinvoices` não têm `invoicenum` | o `#id` é a alternativa |
| 6 | O cabeçalho **público** aparecia por cima do painel | o `StylesheetMiddleware` injecta a casca em todo o HTML | o painel declara `X-Xkhm-Chrome: none` |
| 7 | As tabelas ficavam cortadas no painel | a largura mínima de 640 px dentro de uma coluna de 350 px | a grelha do painel é mais larga, e a tabela rola dentro do contentor |
| 8 | O `id` malformado devolvia a **lista** | o `id` inválido era indistinguível do `id` ausente | `?id=abc` é um **400** |
| 9 | O teste dos screenshots falhava na suite (mas não isolado) | o `pytest-asyncio` abre um loop, e o Playwright **sync** não funciona lá | o browser é um `fixture` de sessão, partilhado |

Cada um deles era um **falso positivo do portão**: a página respondia
`200` com o conteúdo errado, ou `500`.

## Uma nota sobre o processo

O **T012** foi marcado `done` com três dos seus seis critérios de
aceitação por cumprir, e **sem fase de verificação**. O seu próprio
`learn` diz que entregou "the admin authentication layer".

Daí sai a regra que este ticket traz:

> **Um ticket sem `TXXX-verify.md` não é `done`.**

E o kanban passou a dizer `partial` para o que foi parcial.

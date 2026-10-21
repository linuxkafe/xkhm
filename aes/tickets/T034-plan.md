---
ticket: T034
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T034.md
produces:
  - aes/tickets/T034-plan.md
blocked_by: ''
---

# T034 — Plan: O painel de administração

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| O `/admin` só tem um `<h1>` e um link | `public/index.php:290` |
| O T012 foi fechado com 3 ACs por cumprir, e sem `verify` | `aes/tickets/T012.md` (`status: pending`) vs `aes/kanban.md` (`done`) |
| 110 páginas de admin no WHMCS | `ls whmcs-original/admin/*.php` |
| 10 clientes, 90 faturas, 16 pedidos, 52 tickets, 5 domínios | consultas à base real |
| `tblactivitylog` = 2 663 119 linhas | `information_schema` |
| `tblconfiguration` = 235 linhas, colunas `setting`/`value` | `SHOW COLUMNS` |
| O CSS já tem `.table`, `.panel`, `.nav`, grelha, dark mode, media queries | `whmcs.css` |
| `TicketService::addReply` e `CreditService::addCredit` existem | `src/Service/` |

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tem uma porta com um contacto, mas o interior está vazio.
Não há nem uma nota, nem um mapa, nem o livro onde se escreve o que se
vendeu. A porta abre, e não há nada lá dentro.

## Como se fosse um especialista

[FAILURE MODE 1] — O T012 foi marcado `done` com metade dos critérios
por cumprir, e sem fase de verificação. O que falha aqui não é o
código: é o **processo**.

Mecanismo: o `T012-learn.md` diz "Implemented the admin authentication
layer". O kanban diz "Admin panel (core CRUD) — done". Os dois não
falam do mesmo trabalho, e o kanban é o que se lê.

Mitigation: (a) este ticket tem `verify` com evidência por critério;
(b) a verificação passa a ser **obrigatória** para fechar um ticket —
um ticket sem `TXXX-verify.md` não pode ser `done`; (c) o kanban passa
a citar o ficheiro de verificação.

[FAILURE MODE 2] — "Fazer o painel do WHMCS" é 110 páginas. A tentação
é prometer as 110 e entregar 12.

Mecanismo: uma promessa que não se cumpre volta a acontecer — e a
próxima vez ninguém acredita no kanban.

Mitigation: o âmbito é **explícito** — o núcleo operacional, com a
lista de páginas escrita no ticket. E o que fica de fora está
escrito com o mesmo cuidado.

[FAILURE MODE 3] — `tblactivitylog` tem 2 663 119 linhas.

Mecanismo: um `COUNT(*)` ou um `SELECT` sem índice sobre essa tabela
trava o MySQL. Um painel que fica a carregar é um painel que o
operador deixa de abrir.

Mitigation: nenhuma consulta ao painel toca essa tabela. O
"actividade recente" sai de `tblclients.datecreated`,
`tblinvoices.date` e `tbltickets.date` — que têm índice. E há um teste
que **verifica** que o painel não a varre.

[FAILURE MODE 4] — O painel é uma interface de **escrita** sobre dados
de produção. Uma página de lista que aceita `?id=` mal filtrado pode
mostrar a fatura de outro cliente.

Mecanismo: o `id` vem do URL. Sem validação, um `id` negativo ou um
`id` enorme produz um erro de SQL ou — pior — um acesso a dados de
outro cliente.

Mitigation: o `id` é sempre `filter_var(..., FILTER_VALIDATE_INT)`
com um mínimo de 1, e a resposta é um **404 honesto** ("não existe"),
não um 500. O mesmo para a paginação (limite máximo) e para a pesquisa
(a string é parametrizada, nunca concatenada).

[FAILURE MODE 5] — O painel em PHP vai ficar com o aspecto de 2005, ao
lado de uma aplicação Flutter com dark mode e telemóvel.

Mecanismo: o `Layout` é partilhado com a área do cliente — que já não
existe em PHP. O painel precisa da **sua** casca.

Mitigation: um `AdminLayout` próprio, com a **mesma** folha de tokens
(`tokens.css`), as mesmas media queries, e as classes que já
existem. O dark mode vem dos tokens; o telemóvel, de uma navegação
que colapsa.

[ASSUMPTION] Se for falso — "as funções do WHMCS" significa as 110
páginas, incluindo a configuração de gateways, o registo de produtos,
os backups e a automação: consequência: o âmbito é de meses, e o que
entregue hoje não é o que foi pedido.
Validation: o operador disse "completamente vazio" — o problema é a
ausência de funções, não a ausência de uma função específica. E o
dados reais (10 clientes, 90 faturas, 52 tickets) é o que o operador
precisa operar.

[ALTERNATIVE FRAMING] Em vez de "fazer o painel do WHMCS", enquadrar
como "**o que o operador precisa às nove da manhã**": quem me deve
quanto, o que está aberto, quem está a pedir ajuda. O resto é
secundário.
```

### Implementor Phase

```
## Como se fosse uma criança
Ponho um quadro na parede com os números: quantas pessoas entraram,
quanto se ganhou, o que está por pagar. E umas pastas para ver cada
pessoa, cada conta, cada pedido de ajuda. A pasta de "ajuda" tem um
lápis: eu escrevo e o cliente lê.

## Como se fosse um especialista

(D1) A casca: `AdminLayout`.

```php
AdminLayout::render('Clientes', $body, [
    'section' => 'clients',
    'actions' => '<a class="btn" href="/admin/clients.php">…</a>',
]);
```

Navegação lateral com as áreas, um cabeçalho com o utilizador, e o
conteúdo. Dark mode e telemóvel saem dos tokens e das media queries
que já existem.

(D2) As consultas: `AdminRepository`.

Uma classe, **só de leitura**, com as consultas do painel. Cada método
devolve os dados **já em forma canónica** (o `SchemaMap` continua a
ser a única fonte da verdade sobre o esquema).

```php
interface AdminRepositoryInterface
{
    public function countClients(): int;
    public function listClients(string $search, int $limit, int $offset): array;
    public function countClientsMatching(string $search): int;
    public function findClient(int $id): ?array;
    public function listInvoices(array $filters, int $limit, int $offset): array;
    // …
}
```

(D3) A paginação, sempre.

`LIMIT` com um máximo, e o total para o número de páginas. Sem o
total, não há página "próxima".

(D4) As escritas, pelos serviços que já existem.

```php
// responder a um ticket
$id = $this->ticketService->addReply($ticketId, [
    'adminreply' => 1, 'admin' => $adminId, 'message' => $body,
]);

// dar crédito
$this->creditService->addCredit($clientId, $amount, $description);
```

O painel **não escreve directamente** nas tabelas: passa pelos
serviços, que são o caminho testado.

(D5) As rotas, com o `AdminAuthMiddleware` em todas.

```php
$app->get('/admin/clients.php', [$adminClients, 'index'])
    ->add($adminAuthMiddleware);
```

(D6) O `id` é validado, e um `id` inexistente é um 404 honesto.
```

## Addressing FAILURE MODE 1 (o processo)

Mitigation: `T034-verify.md` com evidência **por critério**, e uma
regra nova: **um ticket sem `TXXX-verify.md` não é `done`**. O kanban
passa a citar o ficheiro.

## Addressing FAILURE MODE 2 (as 110 páginas)

Mitigation: a lista de páginas está no ticket, e o que fica de fora
também. A promise é o âmbito escrito.

## Addressing FAILURE MODE 3 (os 2,6 milhões de linhas)

Mitigation: nenhuma consulta toca `tblactivitylog`. A "actividade
recente" vem de `datecreated`/`date`, indexadas. E um teste verifica
que o painel não a varre.

## Addressing FAILURE MODE 4 (o `id` do URL)

Mitigation: validação numérica com mínimo 1, resposta 404 honesta, e
todos os valores parametrizados.

## Addressing FAILURE MODE 5 (o aspecto)

Mitigation: `AdminLayout` próprio, com os tokens existentes.

## [SOLUTION PROPOSAL]

Stage 1 — a casca e o CSS.
Stage 2 — o repositório e as consultas.
Stage 3 — os controladores e as rotas.
Stage 4 — os testes.

## Technical Approach

**Chosen:** um painel PHP com a sua casca, um repositório de leitura,
um controlador por área, e as escritas pelos serviços que já existem.

**What will NOT be implemented:** as 110 páginas; a escrita directa nas
tabas de configuração; a auditoria.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Http/View/AdminLayout.php` | create | A casca do painel |
| `src/Repository/AdminRepositoryInterface.php` | create | As consultas |
| `src/Repository/AdminRepositoryPDO.php` | create | A implementação |
| `src/Http/Controller/Admin/*.php` | create | Um controlador por área |
| `public/index.php` | modify | As rotas |
| `public/templates/default/css/whmcs.css` | modify | O CSS do painel |
| `tests/Integration/AdminRepositoryTest.php` | create | As consultas |
| `tests/Playwright/test_admin_panel.py` | create | O painel, ponta a ponta |

## Testing Strategy

- **As consultas:** contra a base real — contagens, filtros, paginação.
- **O painel:** as páginas respondem, mostram os dados reais, os
  filtros funcionam, a pesquisa encontra, e as **escritas** happen.
- **A navegação e o aspecto:** dark mode e telemóvel.

## Verification Criteria

- [ ] AC1 a AC14 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: medium — é a primeira vez que o painel escreve
- Blocking dependencies: none

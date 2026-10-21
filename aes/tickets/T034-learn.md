---
ticket: T034
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T034-verify.md
---

# T034 — Learn: O painel de administração

## O que se aprendeu

### 1. Um ticket pode ser "feito" e não estar feito

O **T012** — "Admin Panel (Core CRUD)" — está no kanban como `done`.
O seu `learn` diz:

> Implemented the **admin authentication layer** — admin login, session
> management, and route protection.

Dos seis critérios de aceitação, três nunca foram cumpridos: a listagem
de clientes, o detalhe do cliente, a listagem de faturas, a navegação do
painel. E **não houve `T012-verify.md`** — a fase que apanha
precisamente isto não correu.

**Aprendido:** o `learn` é escrito **depois** do trabalho, e por isso
descreve o que foi feito — que pode ser menos do que o ticket pedia.
Quem lê o kanban não lê o `learn`. E o kanban dizia `done`.

A regra que saiu daqui:

> **Um ticket sem `TXXX-verify.md` não é `done`.**

E o kanban passou a dizer `partial` para o que foi parcial. Um kanban
que diz `done` a coisas por fazer deixa de ser um kanban.

### 2. Apagar um ficheiro com o nome de outro é o erro mais caro do dia

Criei `src/Repository/AdminRepositoryInterface.php` com as consultas do
painel. Já existia um ficheiro com **o mesmo nome** — a interface do
`tbladmins`, para a autenticação.

O `write` **sobrescreveu** o original. O PHPStan apanhou logo
(`findByUsername()` indefinido), mas o estrago já estava feito: a
autenticação do administrador deixou de compilar.

E o pior: ao tentar corrigir, fiz `git mv AdminRepositoryPDO.php
AdminPanelRepositoryPDO.php` **antes** de ver o que o ficheiro era — e
o `AdminRepositoryPDO` original também era o da autenticação.

**Aprendido:** antes de `write` num ficheiro que existe, ver o que lá
está. O `git status` mostra `M` (modificado) em vez de `??` (novo) —
e a diferença é um `git checkout` de uma linha. Um `mv` tem de ser
 precededido de `git mv` **e** de uma leitura do conteúdo.

### 3. A camada de vista tem de aceitar o que a base de dados devolve

A base devolve um `userid` como `int`, um `amount` como `string`, um
`date` como `string` e um `status` como `string`. A camada de vista
escreveu `e(?string $value)` — e um `int` é um `TypeError`, num ficheiro
com `strict_types=1`.

O sintoma foi um **500** em duas páginas, e a causa parecia estar na
consulta (que funcionava). O erro estava a três camadas de distância.

**Aprendido:** a assinatura de uma função de vista deve reflectir a
**realidade dos dados**, não a ideia que se tem deles. `mixed` com uma
conversão dentro é mais honesto do que um tipo que rebenta.

### 4. `WHERE` é uma coisa só

O filtro de estado e a pesquisa eram duas cláusulas: a pesquisa
abria um `WHERE (...)` e o estado acrescentava `AND status = ?`. Com
pesquisa **e** estado, funcionava. Com só o estado, o SQL era
`SELECT * FROM tblinvoices AND status = ?` — e um **500**.

**Aprendido:** as condições de uma consulta não são rivais — são uma
lista que se junta. `WHERE (a) AND (b)` ou nada. A
composição explícita não tem o caso "metade das condições".

### 5. Um `id` malformado não é um pedido pela lista

`?id=abc` devolveva a **lista** dos clientes — porque o `id` inválido
era indistinguível do `id` ausente. O operador via uma lista e pensava
que o link estava a funcionar.

**Aprendido:** a distinção entre "não há `id`" e "o `id` está
malformado" é o que separa um `404` de um `400` — e as duas respostas
dizem coisas diferentes ao operador. E nenhuma das duas é a lista.

### 6. O `POST` tem um corpo, e o corpo não é a query

A resposta a um ticket mandava o `id` num campo **escondido** (corpo
do `POST`) e o controlador lia-o da **query**. Resultado: «o ticket não
existe», sempre.

**Aprendido:** num `POST`, os dados vêm no corpo. Um `id` que muda de
lugar entre o formulário e o controlador é um `id` que não funciona. E
o `GET` e o `POST` da mesma página têm de ler o `id` do mesmo sítio.

### 7. O middleware que injecta a casca também tem de saber sair

O `StylesheetMiddleware` garante que **toda** a página HTML tem o
cabeçalho e o rodapé públicos. O painel tem os seus — e o cabeçalho
público por cima era um segundo menu, a apontar para as páginas da
área do cliente, por cima do menu do painel.

**Aprendido:** um invariante como "toda a página HTML tem a casca" é
bom — e precisa de uma **excepção declarada**, não de uma excepção
implícita. A excepção foi um cabeçalho de resposta (`X-Xkhm-Chrome:
none`), explícito, e o middleware respeita-o. A alternativa — reconhecer
o `class="admin-body"` no corpo — seria magia.

### 8. Um guarda estático vale mais do que um guarda em tempo de execução

`tblactivitylog` tem 2,6 milhões de linhas. Nenhuma consulta do painel
pode tocá-la — e "nenhuma" é difícil deprov ar num teste de execução,
porque o teste só vê o que **foi** consultado.

O teste que resolve é **estático**: o código-fonte não pode mencionar a
tabela. E — a lição do próprio teste — o guarda tem de ignorar os
**comentários**, porque o comentário que *diz* que a tabela nunca é
tocada não é uma consulta.

**Aprendido:** para as propriedades que não se manifestam no caminho
feliz, o guarda é **estático**. E um guarda estático que apanha o
próprio comentário é um guarda que falha sem motivo — o que o torna
ignorado.

### 9. O Playwright sync e o `pytest-asyncio` não se misturam

O teste das capturas falhava **na suite** e passava isolado. A
mensagem é reveladora:

> It looks like you are using Playwright Sync API inside the asyncio
> loop.

O `pytest-asyncio` abre um loop asyncio, e o `sync_playwright` não
funciona dentro de um. Isolado, o loop ainda não tinha aberto.

**Aprendido:** quando um teste passa isolado e falha na suite, o
suspeito é o **estado partilhado** — e o `pytest-asyncio` é o
suspeito número um. A solução foi o browser ser um `fixture` de
**sessão**, partilhado, em vez de cada teste abrir o seu.

### 10. A largura mínima de uma tabela é uma decisão de grelha

A tabela tinha `min-width: 640px` dentro de uma coluna da grelha com
350 px. O resultado era uma tabela **cortada** — que rola, mas parece
partida.

**Aprendido:** `min-width` e `grid-template-columns` são a mesma
decisão tomada em dois sítios. A grelha tem de dar à tabela o espaço
que a tabela pede — ou a tabela tem de pedir menos.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O painel é **só de leitura** no repositório | As escritas passam pelos serviços, que são o caminho testado |
| `AdminPanelRepositoryInterface`, e não `AdminRepositoryInterface` | A interface do `tbladmins` já existia — e o nome ia colidir |
| O `id` inválido é um **400**, e o inexistente um **404** | Duas mensagens diferentes para duas situações diferentes |
| O `id` do `POST` é lido do **corpo** | Onde o formulário o põe |
| `X-Xkhm-Chrome: none` | A excepção do middleware é **declarada** |
| `tblactivitylog` fora, por **teste estático** | A propriedade não se manifesta no caminho feliz |
| A grelha do painel é mais larga | A tabela pede 480 px, e a coluna dava 350 |
| O navegador é um `fixture` de sessão | O Playwright sync e o `pytest-asyncio` não se misturam |

## O que fica para depois

- **Dar crédito a um cliente** (AC8): o `CreditService` existe, mas
  aplicar crédito a uma fatura muda o total da fatura — e isso precisa
  de ser **transacional**. Um formulário que mexe no dinheiro sem a
  transação é pior do que não ter.
- **A auditoria**: escrever no `tblactivitylog` quem fez o quê. É uma
  tabela de 2,6 milhões de linhas — escrever nela tem consequências.
- **Marcar uma fatura como paga**: o caminho natural é
  `BillingService`, que ainda não tem.
- **As páginas de configuração**: o `config.php` é de leitura. Criar e
  editar produtos, preços e gateways é trabalho à parte.

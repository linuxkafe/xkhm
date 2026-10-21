# O painel de administração

O painel de administração é a superfície onde o operador passa o dia:
quem me deve quanto, o que está aberto, quem está a pedir ajuda.

Vive em **PHP** (decisão do `T025`: a área do cliente é Flutter, o
painel é PHP), mas usa a **mesma folha de tokens** — por isso segue o
esquema de cor do sistema e é utilizável num ecrã de telemóvel.

---

## As áreas

| Rota | O quê |
|------|-------|
| `/admin` | **Painel** — contagens, receita, tickets abertos, actividade recente |
| `/admin/clients.php` | Clientes: lista, pesquisa, paginação |
| `/admin/clients.php?id=N` | Detalhe: dados, faturas, pedidos, domínios, tickets, transações, alojamento |
| `/admin/invoices.php` | Faturas: lista, filtro por estado, pesquisa, paginação |
| `/admin/invoices.php?id=N` | Detalhe com os itens e o total |
| `/admin/orders.php` | Pedidos: lista e detalhe |
| `/admin/tickets.php` | Tickets: lista e filtro por estado |
| `/admin/tickets.php?id=N` | Detalhe com as respostas, e a caixa para responder |
| `/admin/services.php?id=N` | O detalhe de um serviço: domínio, utilizador, limites, datas |
| `/admin/domains.php` | Domínios |
| `/admin/affiliates.php` | Afiliados |
| `/admin/search.php` | Pesquisa global: clientes, faturas, tickets, domínios, pedidos |
| `/admin/config.php` | A configuração, tal como está na base de dados (leitura) |

Todas exigem sessão de administrador. Sem sessão, a resposta é um `302`
para `/admin/login.php` — **nunca** os dados.

---

## As decisões

### O painel é uma superfície de **leitura**

O `AdminPanelRepository` só lê. As operações que mudam alguma coisa
passam pelos **serviços que já existem**, que são o caminho testado:

- **responder a um ticket** → `TicketService::addReply()`
- (o resto fica para depois)

O painel não escreve directamente nas tabelas. Uma escrita directa
contornaria as regras dos serviços — e os serviços são o que tem
testes.

### O `id` do URL é validado

| Pedido | Resposta |
|--------|----------|
| `?id=84` | a página |
| `?id=99999999` | **404** — «O cliente não existe» |
| `?id=abc` | **400** — «O cliente não é válido» |
| `?id=-5` | **400** |
| sem `id` | a lista |

Um `id` malformado **não** devolve a lista: isso esconderia o erro do
pedido. E um `id` que não existe é um `404` honesto, não um `500`.

### `tblactivitylog` nunca é consultada

A tabela de registo tem **2 663 119 linhas** na base de produção. Um
`COUNT(*)` sobre ela trava o servidor, e o painel é aberto várias
vezes por dia.

A "actividade recente" do painel vem das colunas de data indexadas das
entidades (`datecreated`, `date`). E há um **teste estático** que
rejeita o código que mencione a tabela:

```php
public function testThePanelNeverScansTheActivityLog(): void
```

O teste ignora os comentários: a condição é sobre o **código**, não
sobre o texto que fala do código.

### Nada é concatenado no SQL

O termo de pesquisa e o estado são **parâmetros** vinculados. O teste
faz a prova com uma tentativa de injecção:

```php
$found = $this->repository->listClients("'; DROP TABLE tblclients; --", 25, 0);
// → nada, e a tabela continua lá
```

### A navegação é uma barra, não uma coluna

Num ecrã largo, a navegação é uma barra por baixo do cabeçalho; num
ecrã estreito, é a mesma barra a rolar na horizontal. O painel tem de
servir de um telemóvel, porque é de lá que o operador o usa muitas
vezes.

As tabelas rolam na horizontal dentro do seu contentor — a página
inteira **nunca** tem scroll horizontal.

---

## O que está fora do âmbito

O WHMCS tem **110** páginas de administração. Isto é o **núcleo
operacional**, não uma cópia. Fica de fora, e por escrito:

- a criação e a edição de produtos, preços e gateways
- os relatórios avançados
- a facturação automática
- a auditoria (`tblactivitylog`)

---

## Onde o código está

| Ficheiro | O quê |
|----------|-------|
| `src/Http/View/AdminLayout.php` | A casca: cabeçalho, navegação, conteúdo |
| `src/Repository/AdminPanelRepositoryInterface.php` | As consultas |
| `src/Repository/AdminPanelRepositoryPDO.php` | A implementação, contra o esquema real |
| `src/Http/Controller/Admin/AdminController.php` | A base: paginador, filtros, tabelas, validação |
| `src/Http/Controller/Admin/DashboardController.php` | O painel, a pesquisa e a configuração |
| `src/Http/Controller/Admin/ClientController.php` | Os clientes |
| `src/Http/Controller/Admin/InvoiceController.php` | As faturas |
| `src/Http/Controller/Admin/OrderController.php` | Os pedidos |
| `src/Http/Controller/Admin/TicketController.php` | Os tickets, e a resposta |
| `src/Http/Controller/Admin/DomainController.php` | Os domínios e os afiliados |

> O `AdminRepositoryInterface` (a interface do `tbladmins`, para a
> autenticação) e o `AdminPanelRepositoryInterface` (as consultas do
> painel) são coisas **diferentes**. Confundi-los uma vez, e apagado a
> interface errada — daí o nome mais explícito.

---

## A excepção do cabeçalho público

O `StylesheetMiddleware` garante que toda a página HTML tem o
cabeçalho e o rodapé públicos. O painel **tem os seus** — e o
cabeçalho público por cima do painel seria um segundo menu, a
apontar para as páginas da área do cliente.

Por isso o painel declara a excepção:

```
X-Xkhm-Chrome: none
```

E o middleware respeita-a.

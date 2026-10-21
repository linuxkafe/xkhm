---
ticket: T035
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T035.md
produces:
  - aes/tickets/T035-plan.md
blocked_by: ''
---

# T035 — Plan: Os serviços do cliente, pelo seu tipo

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| `tblhosting` guarda **todos** os serviços | 10 linhas, 3 delas de Backup |
| O **tipo** vem de `tblproducts.type` | `hostingaccount`, `other`, `server` |
| A **família** vem de `tblproductgroups.name` | 6 grupos, incluindo «Backup» |
| O nome real do serviço está no produto | «LINUXKONECT XK10», «Backup Offsite - Servidor Extra» |
| A app não expõe serviços | `GET /api/v1/client/summary` só tem contagens |
| `domainstatus` é um enum | `Pending, Active, Suspended, Terminated, Cancelled, Fraud` |

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tem tres prateleiras com coisas diferentes: Website, Copia de
seguranca, e Servidor. Mas a etiqueta da prateleira diz "Website" em
todas. Quando chego a prateleira da copia de seguranca, a etiqueta
diz-me que e um Website.

## Como se fosse um especialista

[FAILURE MODE 1] — O tipo do serviço pode ser escrito à mão: "se o
produto é do grupo 1, é alojamento web; senão, é outra coisa".

Mecanismo: uma lista de tipos escrita à mão é uma segunda fonte da
verdade sobre o esquema, e desincroniza-se do primeiro produto novo.

Mitigation: o tipo vem **do produto** — `tblproducts.type` e o grupo.
Uma junção, e nada de listas.

[FAILURE MODE 2] — `tblproducts.type` tem três valores
(`hostingaccount`, `other`, `server`) que **não** são o que o operador
chama. "other" não é um tipo; é uma ausência de tipo.

Mecanismo: mostrar "other" na interface é mostrar o esquema.

Mitigation: o valor **bruto** fica para a máquina; o rótulo humano vem
do **grupo** do produto, que é o que o WHMCS mostra ao cliente
(«Web Hosting», «Backup», «Servidores Virtuais»). E o tipo bruto
acompanha, para a administrativo.

[FAILURE MODE 3] — A app mostra os serviços do **outro** lado. O
operador diz "não vejo os serviços subscritos por utilizador" — e a
app é onde o cliente os vê.

Mecanismo: o `summary` só tem contagens. Um cliente com três serviços
não tem onde os ver.

Mitigation: um endpoint novo e um ecrã novo. E o serviço novo
aparece no menu — se não, não existe.

[FAILURE MODE 4] — Os dados de teste não têm serviços, ou têm só de
alojamento. A diferença que se quer mostrar não aparece.

Mecanismo: as contas de teste são criadas pelo `seed_test_accounts.py`,
que não cria serviços.

Mitigation: a semente passa a criar um serviço de **cada** família
relevante, para que o ecrã mostre a diferença — e para que o teste
tenha o que verificar.

[FAILURE MODE 5] — Mostrar o **tipo** não é mostrar o serviço. Um
backup não tem domínio nem utilizador de FTP; um alojamento tem.

Mecanismo: uma tabela com as mesmas colunas para todos os tipos tem
meia dúzia de células vazias.

Mitigation: o detalhe do serviço mostra os campos que **existem**,
com os valores que existem, e esconde os que não se aplicam.
```

### Implementor Phase

```
## Como se fosse uma criança
Cada coisa diz o que e. A prateleira do Website diz "Website" e mostra
o endereco e o utilizador. A da copia diz "Copia de seguranca" e
mostra quanto espaco tem.

## Como se fosse um especialista

(D1) A consulta: o serviço, com o produto e o grupo.

```sql
SELECT h.*, p.name AS productname, p.type AS producttype,
       g.name AS groupname
FROM tblhosting h
LEFT JOIN tblproducts p ON p.id = h.packageid
LEFT JOIN tblproductgroups g ON g.id = p.gid
WHERE h.userid = ?
```

O tipo é o **grupo** (o que o cliente reconhece) e o tipo bruto
acompanha (o que a máquina reconhece).

(D2) Um serviço é um objecto, não uma linha: a resposta tem o serviço,
o produto e o grupo, e a app decide o que mostrar.

(D3) A app: um ecrã de serviços, com o **grupo** como o título de cada
grupo e o nome do produto como subtítulo.

(D4) Os dados de teste: a semente cria um serviço de alojamento **e**
um de backup, para a conta de teste.
```

## Addressing FAILURE MODE 1 (tipos escritos à mão)

Mitigation: o tipo vem da junção com `tblproducts` e
`tblproductgroups`. Não há lista de tipos no código.

## Addressing FAILURE MODE 2 (o "other")

Mitigation: o rótulo humano é o **grupo**; o tipo bruto fica
disponível mas não é o que se mostra.

## Addressing FAILURE MODE 3 (a app)

Mitigation: endpoint novo (`/api/v1/client/services`), ecrã novo, e a
entrada no menu.

## Addressing FAILURE MODE 4 (os dados)

Mitigation: a semente cria serviços de duas famílias.

## Addressing FAILURE MODE 5 (campos a mais)

Mitigation: o detalhe mostra o que o serviço tem; o ecrã da app
agrupa por tipo e mostra os campos comuns e os específicos.

## [SOLUTION PROPOSAL]

Stage 1 — o repositório: os serviços, com o tipo.
Stage 2 — o painel: agrupados, com o detalhe.
Stage 3 — a API e a app: o endpoint e o ecrã.
Stage 4 — a semente e os testes.

## Technical Approach

**Chosen:** o tipo vem do produto, o rótulo vem do grupo, e a
interface mostra o que o serviço tem.

**What will NOT be implemented:** criar ou alterar serviços; a
facturação dos serviços; a configuração de produtos.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Repository/AdminPanelRepositoryPDO.php` | modify | Os serviços, com o produto e o grupo |
| `src/Http/Controller/Admin/ClientController.php` | modify | A secção de serviços |
| `src/Repository/ClientRepositoryPDO.php` | modify | Os serviços do cliente |
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | O endpoint dos serviços |
| `flutter/xkhm_app/lib/screens/services_screen.dart` | create | O ecrã dos serviços |
| `flutter/xkhm_app/lib/services/api_client.dart` | modify | O cliente da API |
| `flutter/xkhm_app/lib/screens/home_shell.dart` | modify | A entrada no menu |
| `tools/seed_test_accounts.py` | modify | Os serviços de teste |
| `tests/Playwright/test_admin_panel.py` | modify | Os tipos no painel |
| `tests/Playwright/test_client_services.py` | create | O ecrã dos serviços |

## Testing Strategy

- O serviço de backup **aparece** como backup, e não como alojamento.
- O endpoint devolve o tipo de cada serviço.
- A app mostra os serviços, agrupados.

## Verification Criteria

- [ ] AC1 a AC7 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: low — é leitura, e uma junção
- Blocking dependencies: none

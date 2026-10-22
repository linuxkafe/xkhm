# A base de dados

Este documento explica, sem rodeios, **o estado da base de dados** e **como
pôr a aplicação a trabalhar contra os dados reais**.

---

## O estado (actualizado)

> A base de dados local `linuxkonect` **é a base de produção**, importada
> do dump `whmcs-original/backupdb.sql` (372 MB, **94 tabelas**, com os
> dados reais: 7 clientes, 88 faturas, 15 pedidos, 4 domínios).
>
> O `docker/init.sql` continua a existir, mas apenas como **fixture de
> desenvolvimento** — o caminho para quando não há dump.

### O dump foi importado — e mudou tudo

A importação revelou que o fixture de desenvolvimento estava construído contra
um esquema que **não existe em produção**. As divergências estão todas em
`src/Repository/SchemaMap.php` (ver `aes/tickets/T028.md`).

E explicou o login: a base real usa **dois** formatos de senha que o
`AuthService` não suportava — bcrypt (admin) e `<md5>:<salt>` (clientes).

---

## Porquê é que o login falha

Não é a senha. São três coisas, por ordem:

### 1. A base de dados de produção não é alcançável (a partir desta máquina)

```
$ ip route get 10.0.3.1
10.0.3.1 via 10.0.0.1 dev eno1 src 10.0.0.111

$ ip route
default via 10.0.0.1 dev eno1
10.0.0.0/24 dev eno1              ← a sub-rede local é 10.0.0.0/24
                                   ← NÃO há rota para 10.0.3.0/24

$ traceroute 10.0.3.1
1  10.0.0.1
2  192.168.1.1                    ← o router também não conhece 10.0.3.0/24
3  37.28.216.2                    ← o pacote segue para a Internet
```

A máquina está em `10.0.0.111/24`. O servidor de base de dados está numa
rede `10.0.3.0/24` que **o gateway por omissão desconhece**. O pacote sai
para a Internet e morre.

**Isto não é firewall, não é credencial, não é a aplicação.** É ausência de
rota. O mesmo bloqueio já estava documentado no projecto whmcs
(`whmcs/aes/tickets/T002-dbfunctions.md`: *"live DB `10.0.3.1:3306`
unreachable from build host"*).

### 2. Não existe dump em lado nenhum

```
$ find /home/seyon/dev -name '*.sql'
/home/seyon/dev/xkhm/docker/init.sql      ← o fixture, não a produção
/home/seyon/dev/pHantasma/tests/schema.sql ← outro projecto
```

A base `whmcs` do contentor `whmcs-mysql` está **vazia** (sem tabelas).

### 3. O utilizador `linuxkafe` não existe localmente

Porque a linha real nunca foi importada. O `linuxkafe@linuxkafe.com` que
existe aqui foi **criado pelo fixture**, para que a suíte de testes tenha uma
conta com que trabalhar.

### Diagnóstico num comando

```bash
make db-check
```

```
1. A base de dados de produção está acessível a partir daqui?
  [FALHA] 10.0.3.1:3306 — a ligação expirou (tempo limite)
          → a base de dados de produção não é alcançável a partir desta
            máquina. Isto NÃO é um problema de credenciais.
  [FALHA] rota de rede
          → adicione uma rota estática para a sub-rede do servidor, ou corra
            a exportação numa máquina que a alcance

2. Há algum dump da base de dados de produção disponível localmente?
  [FALHA] nenhum dump encontrado
          → corra `make db-export` numa máquina que alcance 10.0.3.1

3. A base de dados local tem o esquema esperado?
  [OK]    base local — 16 tabelas em `linuxkonect`
          Estas são as tabelas do FIXTURE DE DESENVOLVIMENTO, não os dados
          de produção.
```

---

## Como pôr os dados reais

> **Já feito.** O dump foi importado e a aplicação corre contra os dados
> reais. O resto desta secção documenta o procedimento, para repetir a
> importação ou para importar de outro servidor.

### Opção A — o dump (funciona sempre)

**1. Numa máquina que alcance `10.0.3.1`** (o próprio servidor, ou um
computador na rede dele):

```bash
cd /home/seyon/dev/xkhm
make db-export
# → docker/dumps/linuxkonect-20260721-120000.sql
```

Ou, se o `mysqldump` não estiver instalado:

```bash
mysqldump -h 10.0.3.1 -u linuxkonect -p'...' \
  --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces \
  linuxkonect > linuxkonect-$(date +%Y%m%d).sql
```

> ⚠️ **O dump contém dados pessoais** (nomes, emails, moradas, IPs). Não o
> versione, não o envie por email e apague-o quando já não for preciso.

**2. Traga o ficheiro para a máquina do xkhm** (por `scp`, ou por qualquer
meio seguro).

**3. Importe:**

```bash
make db-import FILE=docker/dumps/linuxkonect-20260721.sql
```

O import:
1. faz uma cópia de segurança da base local (`docker/backups/`);
2. recria o esquema e aplica o dump — **o dump traz os seus próprios
   `CREATE TABLE`**, portanto o esquema real é aplicado tal e qual e nada é
   inventado;
3. **verifica**: quantas tabelas foram importadas, se as 16 que a aplicação
   usa existem, e quantas linhas há em cada uma.

**4. Verifique:**

```bash
make test        # o SchemaParityTest corre contra os dados reais
make e2e         # a suíte E2E contra os dados importados
```

### Opção B — uma rota de rede (se a máquina ficar atrás do router certo)

Se `10.0.0.1` **tiver** rota para `10.0.3.0/24` (é uma questão de
configurar o router), basta:

```bash
sudo ip route add 10.0.3.0/24 via 10.0.0.1
make db-check    # volta a confirmar a conectividade
```

Se o router **não** tiver essa rota, a Opção A é a via.

### Opção C — réplicação

`mysqldump` de vez em quando, ou uma réplica MySQL. A Opção A é mais
simples e não exige configuração no servidor de produção.

---

## O que a aplicação faz com a base de dados

A aplicação (`xkhm`) é **diretamente compatível** com o esquema WHMCS 5.3.9:
todas as consultas são sobre as tabelas `tbl*` e usam os nomes de coluna do
WHMCS. Não há tabelas próprias, nem colunas próprias, nem uma camada de
tradução.

Isto é garantido por `tests/Integration/SchemaParityTest.php`, que executa
**todas** as consultas dos repositórios contra a base de dados e afirma que
cada coluna referenciada existe.

### As 27 tabelas que a aplicação usa

`tblaccounts`, `tbladmins`, `tblaffiliates`, `tblaffiliateshistory`,
`tblaffiliateswithdrawals`, `tblannouncements`, `tblclients`,
`tblconfiguration`, `tbldomainpricing`, `tbldomains`, `tbldomainspricing`,
`tblhosting`, `tblinvoiceitems`, `tblinvoices`, `tblknowledgebase`,
`tblknowledgebasecats`, `tblnotes`, `tblorders`, `tblpricing`,
`tblproductgroups`, `tblproducts`, `tblservergroups`,
`tblservergroupsrel`, `tblservers`, `tblticketdepartments`,
`tblticketreplies`, `tbltickets`

As restantes tabelas do WHMCS (contas, módulos, promoções,Addon…)
são **preservadas** na importação mas ainda não são usadas pela
aplicação.

### O `sql_mode` é parte do esquema

O MySQL local corre com:

```
STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION
```

**Sem** `NO_ZERO_DATE` — porque a data zero (`0000-00-00`) é a
convenção do WHMCS para «ainda não há data» (a fatura por pagar, com
a data de pagamento vazia), e é o que os **dados importados** têm.

A produção tem de ter o mesmo modo: sem isto, o xkhm não escreve
uma fatura por pagar da maneira que o WHMCS escreve.

### As tabelas do WHMCS são **largas e obrigatórias**

`tblproducts` tem 70 colunas `NOT NULL` **sem default**, e `tblservers`
tem 24. Um `INSERT` com as colunas que o painel conhece é recusado
pelo MySQL em modo estrito:

```
ERROR 1364 (HY000): Field 'description' doesn't have a default value
```

Por isso, as colunas que a tabela exige e que a aplicação não expõe
são lidas do **esquema** (`information_schema`) e preenchidas com o
valor neutro do seu tipo — zero para os números, texto vazio para o
resto. A excepção são as colunas cujo valor neutro seria uma
**mentira** (as enumerações e as datas), que ficam de fora de
propósito: uma ausência tem de falhar em voz alta.

Dois detalhes do esquema do WHMCS que custaram um erro cada:

- `order` é uma **palavra reservada** do MySQL — os identificadores
  dinâmicos vão entre crases.
- `tblhosting` não tem `productid`: o serviço aponta para o produto
  por **`packageid`**.

E o preço de um registo de domínio está em `tblpricing.msetupfee`
(um registo é uma instalação), e não em `annually`.

E o ciclo de faturação chama-se `Annually` (como o cliente e o
produto o escrevem) e a coluna do `tblpricing` chama-se `annually`:
o mapa entre as duas está em `CatalogueRepositoryPDO::CYCLE_COLUMNS`,
e o `-1.00` do `tblpricing` é um **código** («este produto não tem
este ciclo»), não um preço.

---

## A porta da base de dados

A base local **não** ocupa a porta 3306 do host — este host tem um MySQL
próprio (`mariadb.service`) que a usa. O contentor publica a porta
**13306**, apenas no loopback, e só para a suíte de testes:

```yaml
ports:
  - "127.0.0.1:13306:3306"
```

A aplicação fala com a base pela rede do Docker (`host=mysql`), sem porta
publicada. A suíte de testes corre no host e usa `XKHM_DB_PORT=13306`.

## Comandos

| Comando | O que faz |
|---------|-----------|
| `make db-check` | Diagnostica porque é que o login falha |
| `make db-export` | Exporta a base de produção (noutra máquina) |
| `make db-import FILE=…` | Importa um dump e verifica o resultado |
| `make db-backup` | Cópia de segurança da base local |
| `make db-test-accounts` | Cria as contas e os dados de desenvolvimento |
| `make db-seed` | Volta ao fixture de desenvolvimento |
| `make db-reset-tokens` | Lista os tokens de recuperação à espera de entrega |
| `make db-reset-client EMAIL=… PASSWORD=…` | Repõe a senha de um cliente (bcrypt) |
| `make db-reset-admin PASSWORD=…` | Repõe a senha do administrador (bcrypt) |

## As contas

As contas de **produção** nunca são tocadas pela suite de testes:

| Conta | Password | Onde |
|-------|----------|------|
| `linuxkafe` (admin) | a de produção | `tbladmins` |
| os 7 clientes reais | as de produção | `tblclients` |

A suite de testes usa contas de desenvolvimento criadas por
`make db-test-accounts`:

| Conta | Password |
|-------|----------|
| `xkhm-test@example.com` | `xkhm-test-2026` |
| `xkhm-test2@example.com` | `xkhm-test-2026` |
| `xkhm-test` (admin) | `xkhm-test-2026` |
| `xkhm-reset@example.com` | `xkhm-reset-2026` — **só para a recuperação** |
| `xkhm-reset-admin` (admin) | `xkhm-reset-2026` — **só para a recuperação** |

As duas últimas existem porque os testes de recuperação **mudam** a senha
da conta: uma conta partilhada com os outros testes transformaria a
suíte numa roulette.

## Recuperação de credenciais

A aplicação tem um fluxo de recuperação para **clientes** e para
**administradores**. A regra que atravessa os dois: **a resposta é
sempre a mesma** — exista a conta ou não. Um endpoint que responde
diferente é uma oracle de contas.

### O cliente

O token vive nas colunas que o WHMCS 5.3.9 **já tem**:

```
tblclients.pwresetkey     text   ← o token
tblclients.pwresetexpiry  int    ← expira em unix time
```

**Nenhuma coluna nova. Nenhuma tabela nova. Nenhuma migração.**

```bash
curl -X POST http://localhost:8083/api/v1/auth/forgot-password \
  -H 'Content-Type: application/json' \
  -d '{"email":"xkhm-reset@example.com"}'

curl -X POST http://localhost:8083/api/v1/auth/reset-password \
  -H 'Content-Type: application/json' \
  -d '{"token":"<o token>","password":"a-nova-senha"}'
```

O token **expira em uma hora** e é **de uso único**: depois de usado,
`pwresetkey` fica vazio e a mesma segunda tentativa é recusada. A senha
nova é gravada em bcrypt.

### O administrador

`tbladmins` **não tem** colunas de reset, e o esquema de produção não
muda. O token do administrador vive num **ficheiro**, dentro do
contentor:

```
storage/reset-tokens/admin/<token>.json     o token do admin (com expiração)
storage/reset-tokens/pending/<purpose>-*.txt  o token, para entrega
```

```bash
curl -X POST http://localhost:8083/api/v1/admin/auth/forgot-password \
  -H 'Content-Type: application/json' \
  -d '{"username":"xkhm-reset-admin"}'

curl -X POST http://localhost:8083/api/v1/admin/auth/reset-password \
  -H 'Content-Type: application/json' \
  -d '{"token":"<o token>","password":"a-nova-senha"}'
```

A página `/admin/login.php` tem o link **«Esqueci a senha?»**, que abre
o formulário de pedido.

### A entrega do token

A entrega é **plugável** e tem uma regra de posse: **o ficheiro é a fila,
e quem entrega retira-o da fila.** Assim nunca há dois consumidores
(entrega duplicada) nem nenhum (token perdido).

```
a aplicação    →  escreve a fila  →  tenta o SMTP  →  entregou? apaga a fila
                                                     falhou?  a fila fica
correio do host  →  lê a fila  →  entregou? apaga    falhou?  vai para failed/
```

O ficheiro é **sempre** escrito, mesmo quando o SMTP funciona. É a
garantia de que há sempre um caminho para dentro.

#### O correio do host

Há um Postfix nesta máquina, na porta 25, que aceita submissões do
loopback **sem autenticação**. E o contentor da aplicação **não chega ao
host** — o firewall rejeita o tráfego da bridge Docker, e não há `sudo`
para o abrir.

Por isso a entrega é feita **no host**:

```bash
make db-mail-send          # entregar o que estiver pendente
make db-mail-send --dry    # mostrar o que seria enviado
```

E o `crontab` do utilizador corre-o de minuto a minuto:

```
* * * * * /usr/bin/python3 ~/dev/xkhm/tools/mail_pending_tokens.py >> ~/dev/xkhm/storage/mailer.log 2>&1
```

Quando a entrega falha, o ficheiro vai para `storage/reset-tokens/failed/`
**com o motivo** — nada se perde, e o operador vê porque.

#### Os domínios reservados

O mailer **recusa** os domínios reservados para teste (RFC 2606, RFC
6761): `example.com`, `localhost`, `.local`, `.invalid`, … Esses
domínios não existem e não aceitam correio; enviar para lá sai pela
relay, é recusado, e volta como bounce — lixo numa caixa de correio
real, e tráfego na conta de outra pessoa.

A válvula de escape, para quem quiser testar contra um endereço local:

```bash
XKHM_MAILER_ALLOW_RESERVED=1 make db-mail-send
```

#### O link

O e-mail leva um **link** — a acção principal — e o **código**, como
alternativa para quem lê o correio noutro dispositivo.

| Quem | O link |
|------|--------|
| cliente | `{XKHM_PUBLIC_URL}/app/#reset={token}` |
| admin | `{XKHM_PUBLIC_URL}/admin/login.php?action=reset#reset={token}` |

O token viaja no **fragmento** (`#`), que o navegador **nunca envia ao
servidor**: não vai para o log de acessos, nem para um `Referer`. A
aplicação lê-o e **tira-o do endereço de barras** (`replaceState`), para
que também não fique no histórico.

O painel de administração não depende de JavaScript: o campo do
código é visível e editável, e o script só o preenche. Sem JS, o
código do e-mail é colado à mão.

`XKHM_PUBLIC_URL` é o endereço público do sítio — tem de ser o que os
clientes usam, não o do contentor.

#### O remetente

O relay de saída (Brevo) só aceita o remetente que está registado. O
Postfix reescreve o `root` local para esse endereço, via
`/etc/postfix/canonical`. Sem o remetente certo, a resposta é
`451 4.0.0 Invalid from` e a mensagem fica na fila.

| Variável | Onde | Efeito |
|----------|------|--------|
| `XKHM_SMTP_HOST` / `XKHM_SMTP_PORT` | aplicação | o servidor SMTP que a **aplicação** alcança (vazio = não alcança) |
| `XKHM_SMTP_USER` / `XKHM_SMTP_PASS` | aplicação | credenciais — **omitidas** quando não há, porque um `--user` vazio é recusado por um relay que as exige |
| `XKHM_SMTP_FROM` | aplicação | o remetente |
| `XKHM_MAILER_SMTP_HOST` / `XKHM_MAILER_SMTP_PORT` | host | o servidor SMTP que o **correio do host** usa (`127.0.0.1:25`) |
| `XKHM_MAILER_SMTP_FROM` | host | o remetente (`safedata@linuxkafe.com`) |
| `XKHM_MAILER_ALLOW_RESERVED` | host | `1` entrega também aos domínios reservados (só para testes) |
| `APP_ENV=development` | aplicação | devolve o `developmentToken` na resposta |
| `XKHM_RESET_TOKEN_DIR` | ambos | onde ficam os ficheiros dos tokens |

Em produção a resposta é genérica. O administrador lê o token com:

```bash
make db-reset-tokens              # listar
make db-reset-tokens CLEAR=1      # limpar os expirados
make db-reset-tokens CLEAR=1 ALL=1   # limpar todos
```

### As três portas de entrada

A recuperação é **uma das** maneiras de voltar a entrar, e cada uma
funciona mesmo quando as outras estão partidas:

| Porta | Quando funciona |
|-------|-----------------|
| `make db-reset-admin` / `make db-reset-client` | sempre — não passa pela aplicação |
| O fluxo do token | a aplicação está de pé |
| O e-mail | o correio do host está a correr |

É isto que o operador precisava: **um caminho que não dependa da
coisa que se avariou.**

## As credenciais

**No repositório não há senhas.** Há a *forma* da configuração.

O valor vive no `.env`, que **não é versionado** e tem modo `0600`:

```bash
make db-env          # cria o .env com senhas fortes
make db-env REGEN=1  # refaz (e as senhas da base têm de ser rodadas)
```

O `docker-compose.yml` usa `${XKHM_DB_PASSWORD:?...}`: se a variável
faltar, o `docker compose` **recusa arrancar** em vez de inventar uma
senha.

```yaml
- MYSQL_PASSWORD=${XKHM_DB_PASSWORD:?defina XKHM_DB_PASSWORD no .env (make db-env)}
```

### Rodar a senha

A rotação **verifica o resultado**: a senha antiga tem de deixar de
funcionar e a nova tem de funcionar. Sem isso, é uma promessa.

```bash
python3 tools/rotate_db_password.py \
  --host 127.0.0.1 --port 13306 --user linuxkonect \
  --password <a-atual> --root-user root --root-password <a-do-root> \
  --apply
```

```
  a senha antiga ainda funciona? não
  a senha nova funciona?         sim
  [OK]    senha rodada e verificada.
```

Onde a base **de produção** é alcançável, é o mesmo comando com o
`--host` certo. A base de produção (`10.0.3.1`) **não é alcançável
desta máquina** — a rota existe, a porta não responde.

### O hash de encriptação do WHMCS

`CC_ENCRYPTION_HASH` também saiu do repositório, mas **não pode ser
rodado**: é um segredo **partilhado** com o WHMCS, e rodá-lo tornaria
ilegíveis os dados encriptados na base. Precisa de uma migração.

### O histórico do git

A senha de produção esteve em 10 ficheiros e em 10+ commits. Sair dos
ficheiros não a tira do histórico — a **rotação** é que neutraliza a
exposição. Reescrever a história é o que a remove, e é uma operação
destrutiva que afecta todos os clones: é uma decisão do operador, não
uma decisão automática.

## A verificação em dois passos

O segredo do segundo factor vive em `tblclients.authdata` — a coluna
que **já existia** na base de produção, e que o WHMCS 6/7 usa
exactamente para o 2FA. **Nenhuma coluna nova, nenhuma tabela nova.**

O `authdata` guarda um documento JSON com a nossa chave:

```json
{"xkhm_mfa": {"secret": "...", "enabled": true, "created": "..."}}
```

Um `authdata` que **não** é nosso (o módulo de autenticação do
WHMCS) nunca é sobrescrito: a inscrição recusa com uma mensagem
clara.

A **confiança de um dispositivo** não é uma lista: é um cookie
assinado (HMAC-SHA256, com uma chave derivada do `CC_ENCRYPTION_HASH`).
Quem tem o cookie e a assinatura certa é de confiança; revogar um
dispositivo é o cookie expirar.

| Onde | O quê |
|------|-------|
| `POST /api/v1/client/security/enrol` | o segredo e o QR |
| `POST /api/v1/client/security/confirm` | a confirmação, com o código |
| `POST /api/v1/client/security/disable` | desativar |
| `POST /api/v1/auth/login` | com `deviceId`; pode devolver um **desafio** |
| `POST /api/v1/auth/mfa` | a resposta ao desafio |

## O aviso de pagamento

O que o cliente recebe **não** é uma fatura: é um **aviso de
pagamento**, e o documento diz isso na cara.

> Este documento é um aviso de pagamento. Não é uma fatura, nem um
> documento fiscal, e não serve de título para efeitos fiscais. A
> fatura certificada é emitida separadamente, pelo programa de
> faturação certificado.

Em Portugal, a fatura é o documento fiscal, e só o software
**certificado** pela Autoridade Tributária pode emitir um. Um
documento que se apresente como fatura e não é é um problema — e o
problema é do software, não do cliente.

| | |
|---|---|
| O PDF | `GET /api/v1/client/invoices/{id}/pdf` (o cliente dono) e `GET /admin/invoices/{id}/pdf` (o administrador) |
| O reenvio | `POST /api/v1/client/invoices/{id}/resend` e `POST /admin/invoices/{id}/resend` — vai para a fila do correio do host, **com o PDF anexado** |
| O gerador | `dompdf` (HTML → PDF), pelo composer |

O documento **não recalcula** o IVA: mostra o que a fatura tem. A
tabela de taxas (`tbltax`) está vazia, e inventar uma taxa é pior do
que mostrar o total que a fatura tem.

## A configuração

As definições vivem na **`tblconfiguration`** — a tabela do WHMCS, e a
que veio importada. São **partilhadas** com o WHMCS: uma mudança feita
numa é vista pela outra.

O `configuration.php` ficou com o que é **dela**: a ligação à base de
dados, o hash de encriptação e o caminho da administração.

O painel (`/admin/config.php`) mostra-as **agrupadas** — Empresa,
Registo do cliente, IVA, Moeda, Facturação, Numeração, Correio,
Pagamento — e edita as que interessam. As que não estão na lista
explícita das editáveis são de leitura: `InstanceID`, `License` e
`token_namespace` não se mexem.

E no topo há o **relatório do que falta** para o documento de
pagamento ficar completo — que hoje inclui o **NIF**, os **dados
bancários**, a **informação de pagamento** e a **moeda**.

### A empresa resolve dois sítios

O WHMCS guarda a empresa em `Company*` **e** em `RegistrarAdmin*` (o
registador pode facturar ao cliente com os seus dados). A resolução
procura em `Company*` e, quando está vazio, vai ao `RegistrarAdmin*` —
que é de onde vem, nesta instalação.

### A escrita

A `tblconfiguration.setting` é um `text` com um índice **não-único**:
**não há chave única**, e o `ON DUPLICATE KEY` nunca dispara. A escrita
é, por isso, apagar e escrever — e fica **exactamente uma** linha.

## O preço e o prazo de renovação

Um serviço tem um **preço** (`tblhosting.amount`) e um **ciclo**
(`tblhosting.billingcycle`), e a próxima renovação
(`tblhosting.nextduedate`).

O preço **por ciclo** está no produto (`tblpricing`): `monthly`,
`quarterly`, `semiannually`, `annually`, `biennially`, `triennially` —
e `-1.00` significa que o produto não tem esse ciclo. Só os ciclos com
preço entram no formulário.

Mudar o preço a meio do período dá origem a um **prorrateio**: a
diferença, proporcional ao tempo que resta do ciclo. É **cotado antes**
de ser aplicado, e o valor aplicado é o que foi mostrado.

- Uma diferença **a favor do cliente** vira **crédito** (`tblclients.credit`).
- Uma diferença **a favor da empresa** fica **registada** para a
  próxima fatura — não há coluna para ela, e o painel diz isso.
- A mudança é escrita nas **notas do cliente** (`tblnotes`), como no
  WHMCS.

## Formatos de senha

A base de produção usa **dois** formatos. O verificador suporta ambos (e
mais alguns, para implantações mais antigas):

| Formato | Quem o usa |
|---------|-----------|
| bcrypt `$2y$…` | o admin `linuxkafe` |
| `<md5>:<sal>` | os 7 clientes |

As senhas **novas** são sempre bcrypt. Ver `src/Service/PasswordHasher.php`.

### A conta de administrador tem DOIS hashes

A tabela `tbladmins` do WHMCS guarda **duas** colunas de hash: `password` e
`passwordhash`. O WHMCS escreve uma delas na migração e a outra numa
mudança de senha posterior — e a conta `linuxkafe` tem **dois bcrypt
diferentes**, um em cada coluna.

Por isso a verificação tenta **as duas**. Se a senha do operador estiver em
`passwordhash` e a aplicação só lesse `password`, o login falha com a senha
certa — um sintoma indistinguível de "senha errada".

Diagnóstico:

```bash
make db-check
```

que mostra o estado da conta e o formato de cada hash.

### Se a senha continuar a falhar

A senha de produção é forte e não é derivável. A via garantida é repô-la:

```bash
make db-reset-admin PASSWORD='a-nova-senha'
```

Isso escreve bcrypt em **ambas** as colunas da conta local. A conta de
produção em `xk.linuxkafe.com` **não** é tocada — é preciso um reset
lá, com o WHMCS.

---

## As contas e as suas senhas

| Conta | Onde | Password |
|-------|------|----------|
| `admin` | `tbladmins` | a que estiver na base importada |
| `linuxkafe@linuxkafe.com` | `tblclients` | a que estiver na base importada |
| `admin` / `admin123` | fixture local | `admin123` |
| `linuxkafe@linuxkafe.com` / `linuxkafe123` | fixture local | `linuxkafe123` |

Depois de importar a base real, **as senhas são as de lá** — as do fixture
desaparecem porque as tabelas são recriadas.

E a senha da **base de dados** é outra história: vive no `.env`, não no
repositório. Ver [As credenciais](#as-credenciais).

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

### As 16 tabelas que a aplicação usa

`tbladmins`, `tblaffiliates`, `tblaffiliatesclients`, `tblaffiliatespayouts`,
`tblannouncements`, `tblclients`, `tbldomainpricing`, `tbldomains`,
`tblinvoiceitems`, `tblinvoices`, `tblknowledgebase`, `tblknowledgebasecats`,
`tblorders`, `tblticketdepartments`, `tblticketreplies`, `tbltickets`

As restantes tabelas do WHMCS (produtos, alojamentos, servidores, contas…)
são **preservadas** na importação mas ainda não são usadas pela aplicação.

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

Não há SMTP configurado. A entrega é **plugável** e tem uma garantia:
o ficheiro é **sempre** escrito, mesmo quando o SMTP existe. Assim há
sempre um caminho para dentro, e o e-mail é um extra — não a condição.

| Variável | Efeito |
|----------|--------|
| `XKHM_SMTP_HOST` | activa o envio por SMTP |
| `XKHM_SMTP_PORT` | porta (por omissão `587`) |
| `XKHM_SMTP_USER` / `XKHM_SMTP_PASS` | credenciais |
| `XKHM_SMTP_FROM` | remetente |
| `APP_ENV=development` | devolve o `developmentToken` na resposta |
| `XKHM_RESET_TOKEN_DIR` | onde ficam os ficheiros dos tokens |

Em produção a resposta é genérica. O administrador lê o token com:

```bash
make db-reset-tokens              # listar
make db-reset-tokens CLEAR=1      # limpar os expirados
make db-reset-tokens CLEAR=1 ALL=1   # limpar todos
```

### As três portas de entrada

A recuperação é uma **das três** maneiras de voltar a entrar, e cada uma
funciona mesmo quando as outras estão partidas:

| Porta | Quando funciona |
|-------|-----------------|
| `make db-reset-admin` / `make db-reset-client` | sempre — não passa pela aplicação |
| O fluxo do token | a aplicação está de pé |
| O e-mail | há SMTP configurado |

É isto que o operador precisava: **um caminho que não dependa da
coisa que se avariou.**

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

## As credenciais

| Conta | Onde | Password |
|-------|------|----------|
| `admin` | `tbladmins` | a que estiver na base importada |
| `linuxkafe@linuxkafe.com` | `tblclients` | a que estiver na base importada |
| `admin` / `admin123` | fixture local | `admin123` |
| `linuxkafe@linuxkafe.com` / `linuxkafe123` | fixture local | `linuxkafe123` |

Depois de importar a base real, **as senhas são as de lá** — as do fixture
desaparecem porque as tabelas são recriadas.
